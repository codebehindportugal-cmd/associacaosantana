<?php

namespace App\Http\Controllers;

use App\Mail\InscricaoConfirmadaMail;
use App\Models\Evento;
use App\Models\EventoInscricao;
use App\Services\VivaPayments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

    public function store(Request $request, Evento $evento): RedirectResponse|SymfonyResponse
    {
        abort_unless($evento->inscricoes_ativas && $evento->estado === 'publicado', 404);

        $opcoes = $evento->opcoesInscricao();
        $nomesOpcoes = array_column($opcoes, 'nome');

        $viva = app(VivaPayments::class);
        $pagamentoOnline = $evento->inscricoes_pagamento_online && $viva->configurado();

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'telefone' => ['required', 'string', 'max:30'],
            'email' => [$pagamentoOnline ? 'required' : 'nullable', 'email', 'max:150'],
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

        $inscricao = $evento->inscricoes()->create($data);

        // Pagamento online via Viva Smart Checkout
        if ($pagamentoOnline && $inscricao->valor_estimado > 0) {
            try {
                $orderCode = $viva->criarOrdem($inscricao);
                $inscricao->update([
                    'pagamento_estado' => 'pendente',
                    'pagamento_order_code' => $orderCode,
                ]);

                // Redirect externo (Inertia)
                return Inertia::location($viva->urlCheckout($orderCode));
            } catch (\Throwable $e) {
                Log::error('Viva: falha a criar ordem de pagamento', ['inscricao' => $inscricao->id, 'erro' => $e->getMessage()]);
                // Inscrição fica válida com pagamento no dia
                $inscricao->update(['pagamento_estado' => null]);
            }
        }

        $this->enviarConfirmacao($inscricao);

        return back()->with('success', 'Inscrição registada! Até já 🎉');
    }

    /**
     * Retorno do Smart Checkout (URL de sucesso). Verifica a transação na API.
     */
    public function pagamentoRetorno(Request $request, VivaPayments $viva): RedirectResponse
    {
        $transactionId = $request->query('t');
        $orderCode = $request->query('s');

        $inscricao = EventoInscricao::where('pagamento_order_code', $orderCode)->first();

        if ($inscricao && $transactionId && $viva->transacaoPaga($transactionId)) {
            $inscricao->update(['pagamento_estado' => 'pago', 'pago_em' => now()]);
            $this->enviarConfirmacao($inscricao);

            return redirect()->route('inscricoes.index')->with('success', 'Pagamento confirmado! Inscrição concluída 🎉');
        }

        Log::warning('Viva: retorno sem confirmação', ['t' => $transactionId, 's' => $orderCode]);

        return redirect()->route('inscricoes.index')->with('success', 'Inscrição registada. O pagamento está a ser processado — receberás confirmação por email.');
    }

    /**
     * Retorno do Smart Checkout em caso de falha/cancelamento.
     */
    public function pagamentoFalha(Request $request): RedirectResponse
    {
        $inscricao = EventoInscricao::where('pagamento_order_code', $request->query('s'))->first();
        $inscricao?->update(['pagamento_estado' => 'falhado']);

        return redirect()->route('inscricoes.index')->withErrors([
            'pagamento' => 'O pagamento não foi concluído. A inscrição fica registada — podes pagar no dia do evento ou tentar de novo.',
        ]);
    }

    private function enviarConfirmacao(EventoInscricao $inscricao): void
    {
        try {
            if ($inscricao->email) {
                Mail::to($inscricao->email)->send(new InscricaoConfirmadaMail($inscricao->load('evento')));
            }

            if ($aviso = config('mail.contact_to')) {
                Mail::raw(
                    "Nova inscrição em {$inscricao->evento->titulo}:\n".
                    "Nome: {$inscricao->nome}\nTelefone: {$inscricao->telefone}\n".
                    'Pessoas: '.$inscricao->num_pessoas.($inscricao->opcao ? "\nOpção: {$inscricao->opcao}" : '').
                    ($inscricao->valor_estimado !== null ? "\nValor: {$inscricao->valor_estimado} € (".($inscricao->pagamento_estado === 'pago' ? 'PAGO ONLINE' : 'paga no dia').')' : ''),
                    fn ($m) => $m->to($aviso)->subject('Nova inscrição: '.$inscricao->evento->titulo)
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar emails de inscrição', ['inscricao' => $inscricao->id, 'erro' => $e->getMessage()]);
        }
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

            $ok = (bool) ($resposta['success'] ?? false);

            if (! $ok) {
                \Log::warning('reCAPTCHA falhou', [
                    'error_codes' => $resposta['error-codes'] ?? [],
                    'hostname' => $resposta['hostname'] ?? null,
                    'token_presente' => $request->filled('recaptcha_token'),
                ]);
            }

            return $ok;
        } catch (\Throwable $e) {
            // Falha de rede na verificação: não bloquear inscrições
            \Log::warning('reCAPTCHA: erro de rede na verificação', ['erro' => $e->getMessage()]);

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
            'pagamento_online' => $evento->inscricoes_pagamento_online && app(VivaPayments::class)->configurado(),
            'esgotado' => $esgotado,
            'vagas_restantes' => $evento->inscricoes_limite !== null
                ? max(0, $evento->inscricoes_limite - $evento->totalPessoasInscritas())
                : null,
        ];
    }
}
