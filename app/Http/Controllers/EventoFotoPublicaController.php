<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\EventoMedia;
use App\Rules\Recaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Fotos enviadas pelo público para um evento.
 * Ficam guardadas fora da pasta pública (storage/app/private/fotos-pendentes)
 * e só passam para /images/events/uploads depois de aprovadas no backoffice.
 */
class EventoFotoPublicaController extends Controller
{
    private const PASTA_PENDENTES = 'fotos-pendentes';

    // ---------- Público ----------

    public function show(Evento $evento): Response
    {
        $this->garantirAberto($evento);

        return Inertia::render('Eventos/EnviarFotos', [
            'evento' => [
                'id' => $evento->id,
                'titulo' => $evento->titulo,
                'subtitulo' => $evento->subtitulo ?: $evento->localizacao,
                'cartaz' => $evento->cartaz,
                'data' => optional($evento->data_inicio)->format('d/m/Y') ?: $evento->periodo,
            ],
            'maxFotos' => 20,
        ]);
    }

    /**
     * Recebe UMA foto por pedido (o formulário envia-as uma a uma),
     * para não esbarrar nos limites de upload do PHP.
     */
    public function store(Request $request, Evento $evento): JsonResponse
    {
        $this->garantirAberto($evento);

        $data = $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'nome' => ['required', 'string', 'max:120'],
            'contacto' => ['nullable', 'string', 'max:160'],
            'autorizo' => ['accepted'],
            'recaptcha_token' => [new Recaptcha],
        ], [
            'foto.image' => 'O ficheiro tem de ser uma fotografia.',
            'foto.mimes' => 'Formato não suportado (usa JPG, PNG ou WEBP).',
            'foto.max' => 'A fotografia é demasiado grande (máx. 15 MB).',
            'autorizo.accepted' => 'É preciso autorizar a publicação das fotos.',
        ]);

        $ficheiro = $request->file('foto');
        $extensao = strtolower($ficheiro->extension() ?: $ficheiro->getClientOriginalExtension() ?: 'jpg');
        $caminho = Storage::disk('local')->putFileAs(self::PASTA_PENDENTES, $ficheiro, Str::uuid().'.'.$extensao);

        if (! $caminho) {
            return response()->json(['message' => 'Não foi possível guardar a fotografia. Tenta novamente.'], 500);
        }

        $media = $evento->todaMedia()->create([
            'tipo' => 'foto',
            'caminho' => $caminho,
            'titulo' => 'Foto de '.$data['nome'],
            'origem' => 'publico',
            'aprovado' => false,
            'enviado_nome' => $data['nome'],
            'enviado_contacto' => $data['contacto'] ?? null,
            'ordem' => 0,
        ]);

        $this->avisarAssociacao($evento, $data['nome']);

        return response()->json(['ok' => true, 'id' => $media->id]);
    }

    // ---------- Backoffice ----------

    /** Mostra uma foto pendente (só para utilizadores autenticados). */
    public function ver(EventoMedia $media): BinaryFileResponse|RedirectResponse
    {
        if ($media->aprovado) {
            return redirect($media->caminho);
        }

        $disco = Storage::disk('local');
        abort_unless($disco->exists($media->caminho), 404);

        return response()->file($disco->path($media->caminho));
    }

    public function aprovar(EventoMedia $media): RedirectResponse
    {
        $this->aprovarMedia($media);

        return back()->with('success', 'Foto aprovada e publicada.');
    }

    public function aprovarTodas(Evento $evento): RedirectResponse
    {
        $total = 0;
        $evento->mediaPendente()->get()->each(function (EventoMedia $media) use (&$total) {
            if ($this->aprovarMedia($media)) {
                $total++;
            }
        });

        return back()->with('success', "{$total} foto(s) aprovada(s) e publicada(s).");
    }

    // ---------- Auxiliares ----------

    private function garantirAberto(Evento $evento): void
    {
        abort_unless($evento->estado === 'publicado' && $evento->fotos_publico_ativo, 404);
    }

    private function aprovarMedia(EventoMedia $media): bool
    {
        if ($media->aprovado) {
            return false;
        }

        $disco = Storage::disk('local');
        if (! $disco->exists($media->caminho)) {
            Log::warning('Foto pendente sem ficheiro', ['media' => $media->id, 'caminho' => $media->caminho]);

            return false;
        }

        $destino = public_path('images/events/uploads');
        File::ensureDirectoryExists($destino);
        $nome = basename($media->caminho);
        File::move($disco->path($media->caminho), $destino.'/'.$nome);

        $media->update([
            'caminho' => '/images/events/uploads/'.$nome,
            'aprovado' => true,
            'ordem' => EventoMedia::where('evento_id', $media->evento_id)->where('aprovado', true)->max('ordem') + 1,
        ]);

        return true;
    }

    /** Um email por evento no máximo a cada 30 minutos, para não encher a caixa. */
    private function avisarAssociacao(Evento $evento, string $nome): void
    {
        $destino = config('mail.contact_to');
        if (! $destino || ! Cache::add("fotos-publico-aviso-{$evento->id}", true, now()->addMinutes(30))) {
            return;
        }

        try {
            Mail::raw(
                "Chegaram fotos novas para o evento \"{$evento->titulo}\" (enviadas por {$nome}).\n\n".
                "Estão à espera de aprovação no backoffice:\n".route('eventos.edit', $evento->id),
                fn ($m) => $m->to($destino)->subject("Fotos por aprovar: {$evento->titulo}")
            );
        } catch (\Throwable $e) {
            Log::warning('Falha ao avisar fotos pendentes', ['evento' => $evento->id, 'erro' => $e->getMessage()]);
        }
    }
}
