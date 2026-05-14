<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\EventoMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventoController extends Controller
{
    public function publicShow(Evento $evento): Response
    {
        abort_unless($evento->estado === 'publicado', 404);

        return Inertia::render('Eventos/PublicShow', [
            'evento' => $this->eventoData($evento->load('media')),
        ]);
    }

    public function index(): Response
    {
        return Inertia::render('Eventos/Index', [
            'eventos' => Evento::with('media')
                ->orderByDesc('data_inicio')
                ->orderBy('ordem')
                ->get()
                ->map(fn (Evento $evento) => $this->eventoData($evento)),
        ]);
    }

    public function show(Evento $evento): Response
    {
        return Inertia::render('Eventos/Show', [
            'evento' => $this->eventoData($evento->load('media')),
        ]);
    }

    public function edit(Evento $evento): Response
    {
        return Inertia::render('Eventos/Edit', [
            'evento' => $this->eventoData($evento->load('media')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        unset($data['programa_texto'], $data['cartaz']);
        $data['ordem'] ??= 0;
        $data['destaque'] ??= false;
        $data['cartaz'] = $this->guardarFicheiro($request, 'cartaz') ?? null;
        $data['programa'] = $this->programaFromText($request->string('programa_texto')->toString(), $data);

        Evento::create($data);

        return back()->with('success', 'Evento criado.');
    }

    public function update(Request $request, Evento $evento): RedirectResponse
    {
        $data = $this->validatedData($request);
        unset($data['programa_texto'], $data['cartaz']);
        $data['ordem'] ??= 0;
        $data['destaque'] ??= false;
        $data['programa'] = $this->programaFromText($request->string('programa_texto')->toString(), $data);

        if ($cartaz = $this->guardarFicheiro($request, 'cartaz')) {
            $this->apagarFicheiroPublico($evento->cartaz);
            $data['cartaz'] = $cartaz;
        }

        $evento->update($data);

        return back()->with('success', 'Evento atualizado.');
    }

    public function destroy(Evento $evento): RedirectResponse
    {
        $this->apagarFicheiroPublico($evento->cartaz);
        $evento->media->each(fn (EventoMedia $media) => $this->apagarFicheiroPublico($media->caminho));
        $evento->delete();

        return back()->with('success', 'Evento apagado.');
    }

    public function storeMedia(Request $request, Evento $evento): RedirectResponse
    {
        $request->validate([
            'ficheiros' => ['required', 'array'],
            'ficheiros.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov,webm', 'max:51200'],
        ]);

        $ordemInicial = $evento->media()->count();

        foreach ($request->file('ficheiros', []) as $index => $ficheiro) {
            $evento->media()->create([
                'tipo' => str_starts_with((string) $ficheiro->getMimeType(), 'video/') ? 'video' : 'foto',
                'caminho' => $this->moverUpload($ficheiro, 'events/uploads'),
                'titulo' => pathinfo($ficheiro->getClientOriginalName(), PATHINFO_FILENAME),
                'ordem' => $ordemInicial + $index,
            ]);
        }

        return back()->with('success', 'Ficheiros adicionados ao evento.');
    }

    public function destroyMedia(EventoMedia $media): RedirectResponse
    {
        $this->apagarFicheiroPublico($media->caminho);
        $media->delete();

        return back()->with('success', 'Ficheiro removido.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'subtitulo' => ['nullable', 'string', 'max:255'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'periodo' => ['nullable', 'string', 'max:255'],
            'localizacao' => ['nullable', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'cartaz' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'facebook_post_url' => ['nullable', 'url', 'max:2048'],
            'estado' => ['required', 'in:rascunho,publicado'],
            'destaque' => ['boolean'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'programa_texto' => ['nullable', 'string'],
        ]);
    }

    private function eventoData(Evento $evento): array
    {
        return [
            'id' => $evento->id,
            'titulo' => $evento->titulo,
            'subtitulo' => $evento->subtitulo,
            'data_inicio' => optional($evento->data_inicio)->toDateString(),
            'data_fim' => optional($evento->data_fim)->toDateString(),
            'periodo' => $evento->periodo,
            'localizacao' => $evento->localizacao,
            'badge' => $evento->badge,
            'descricao' => $evento->descricao,
            'cartaz' => $evento->cartaz,
            'facebook_post_url' => $evento->facebook_post_url,
            'programa' => $evento->programa ?? [],
            'estado' => $evento->estado,
            'destaque' => $evento->destaque,
            'ordem' => $evento->ordem,
            'created_at' => optional($evento->created_at)->format('d/m/Y H:i'),
            'updated_at' => optional($evento->updated_at)->format('d/m/Y H:i'),
            'media' => $evento->media->map(fn (EventoMedia $media) => [
                'id' => $media->id,
                'tipo' => $media->tipo,
                'caminho' => $media->caminho,
                'titulo' => $media->titulo,
            ])->values(),
        ];
    }

    private function programaFromText(string $texto, array $data): array
    {
        $items = collect(preg_split('/\R/u', $texto))
            ->map(fn ($linha) => trim((string) $linha))
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return [];
        }

        return [[
            'day' => $data['data_inicio'] ?? ($data['periodo'] ?? 'Programa'),
            'label' => $data['badge'] ?? 'Programa',
            'items' => $items,
        ]];
    }

    private function guardarFicheiro(Request $request, string $campo): ?string
    {
        if (! $request->hasFile($campo)) {
            return null;
        }

        return $this->moverUpload($request->file($campo), 'events/uploads');
    }

    private function moverUpload($ficheiro, string $pasta): string
    {
        $destino = public_path("images/{$pasta}");
        File::ensureDirectoryExists($destino);

        $nome = Str::uuid().'.'.$ficheiro->getClientOriginalExtension();
        $ficheiro->move($destino, $nome);

        return "/images/{$pasta}/{$nome}";
    }

    private function apagarFicheiroPublico(?string $caminho): void
    {
        if (! $caminho || ! str_starts_with($caminho, '/images/events/uploads/')) {
            return;
        }

        File::delete(public_path(ltrim($caminho, '/')));
    }
}
