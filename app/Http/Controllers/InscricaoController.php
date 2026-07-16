<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\EventoInscricao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página pública de inscrições em eventos.
 * URL fixa /inscricoes — o QR dos cartazes aponta sempre para aqui.
 */
class InscricaoController extends Controller
{
    public function index(): Response
    {
        $eventos = Evento::publicados()
            ->where('inscricoes_ativas', true)
            ->orderBy('data_inicio')
            ->get()
            ->map(fn (Evento $evento) => $this->eventoParaPagina($evento));

        return Inertia::render('Public/Inscricoes', [
            'eventos' => $eventos,
            'recaptchaSiteKey' => config('services.recaptcha.site_key'),
        ]);
    }

    public function store(Request $request, Evento $evento): RedirectResponse
    {
        abort_unless($evento->inscricoes_ativas && $evento->estado === 'publicado', 404);

        $opcoes = $evento->opcoesInscricao();
        $nomesOpcoes = array_column($opcoes, 'nome');

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'telefone' => ['required', 'string', 'max:30'],
            'num_pessoas' => ['required', 'integer', 'min:1', 'max:50'],
            'opcao' => [count($nomesOpcoes) ? 'required' : 'nullable', 'string', count($nomesOpcoes) ? 'in:'.implode(',', $nomesOpcoes) : 'max:150'],
            'num_criancas' => ['nullable', 'integer', 'min:0', 'max:30'],
            'idades_criancas' => ['nullable', 'string', 'max:150'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            'recaptcha_token' => [config('services.recaptcha.secret_key') ? 'required' : 'nullable', 'string'],
        ]);

        if (! $this->validarRecaptcha($request)) {
            return back()->withErrors(['recaptcha_token' => 'Validação anti-robô falhou. Tenta novamente.']);
        }

        // Verificar vagas (limite conta pessoas, não inscrições)
        if ($evento->inscricoes_limite !== null) {
            $ocupadas = $evento->totalPessoasInscritas();
            if ($ocupadas + (int) $data['num_pessoas'] > $evento->inscricoes_limite) {
                $restantes = max(0, $evento->inscricoes_limite - $ocupadas);
                return back()->withErrors([
                    'num_pessoas' => $restantes
                        ? "Só restam {$restantes} vagas."
                        : 'As vagas esgotaram.',
                ]);
            }
        }

        unset($data['recaptcha_token']);
        $data['valor_estimado'] = $this->calcularValor($evento, $data, $opcoes);
        $evento->inscricoes()->create($data);

        return back()->with('success', 'Inscrição registada! Até já 🎉');
    }

    /**
     * Valor estimado: preço da opção escolhida (se tiver) ou preço base do evento.
     * Crianças pagam o preço de criança quando definido.
     */
    private function calcularValor(Evento $evento, array $data, array $opcoes): ?float
    {
        $precoAdulto = null;

        if (! empty($data['opcao'])) {
            $opcao = collect($opcoes)->firstWhere('nome', $data['opcao']);
            $precoAdulto = $opcao['preco'] ?? null;
        }

        $precoAdulto ??= $evento->inscricoes_preco !== null ? (float) $evento->inscricoes_preco : null;

        if ($precoAdulto === null) {
            return null;
        }

        $criancas = min((int) ($data['num_criancas'] ?? 0), (int) $data['num_pessoas']);
        $adultos = max(0, (int) $data['num_pessoas'] - $criancas);
        $precoCrianca = $evento->inscricoes_preco_crianca !== null
            ? (float) $evento->inscricoes_preco_crianca
            : $precoAdulto;

        return round($adultos * $precoAdulto + $criancas * $precoCrianca, 2);
    }

    private function validarRecaptcha(Request $request): bool
    {
        $secret = config('services.recaptcha.secret_key');

        // Sem chaves configuradas: captcha desativado
        if (! $secret) {
            return true;
        }

        try {
            $resposta = Http::asForm()->timeout(10)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $request->string('recaptcha_token')->toString(),
                'remoteip' => $request->ip(),
            ])->json();

            return (bool) ($resposta['success'] ?? false);
        } catch (\Throwable) {
            // Falha de rede na verificação: não bloquear inscrições
            return true;
        }
    }

    private function eventoParaPagina(Evento $evento): array
    {
        $esgotado = $evento->vagasEsgotadas();

        return [
            'id' => $evento->id,
            'titulo' => $evento->titulo,
            'subtitulo' => $evento->subtitulo,
            'data_inicio' => $evento->data_inicio?->format('d/m/Y'),
            'periodo' => $evento->periodo,
            'localizacao' => $evento->localizacao,
            'cartaz' => $evento->cartaz,
            'opcoes' => $evento->opcoesInscricao(),
            'pede_idades' => $evento->inscricoes_pede_idades,
            'preco' => $evento->inscricoes_preco !== null ? (float) $evento->inscricoes_preco : null,
            'preco_crianca' => $evento->inscricoes_preco_crianca !== null ? (float) $evento->inscricoes_preco_crianca : null,
            'idade_crianca' => $evento->inscricoes_idade_crianca,
            'esgotado' => $esgotado,
            'vagas_restantes' => $evento->inscricoes_limite !== null
                ? max(0, $evento->inscricoes_limite - $evento->totalPessoasInscritas())
                : null,
        ];
    }
}
