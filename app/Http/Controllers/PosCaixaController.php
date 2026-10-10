<?php

namespace App\Http\Controllers;

use App\Models\CaixaDiaria;
use App\Models\PosSession;
use App\Services\CaixaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Caixa no proprio POS (bar/cafe e restaurante): leitura sem fechar, fecho com
 * o PIN do posto e o talao. O talao sai na impressora do posto.
 */
class PosCaixaController extends Controller
{
    public function __construct(private CaixaService $caixas)
    {
    }

    /** POS do restaurante fecha a caixa "Restaurante"; o bar fecha a do seu ponto. */
    public static function pontoDoPosto(): string
    {
        return session('pos_tipo') === 'restaurante'
            ? 'Restaurante'
            : (string) (session('pos_localizacao') ?: session('pos_nome'));
    }

    /** Resumo da caixa aberta deste posto, para o painel do POS. */
    public static function resumoDoPosto(): ?array
    {
        $caixa = CaixaDiaria::abertaParaPonto(self::pontoDoPosto());

        return $caixa ? [
            'id' => $caixa->id,
            'ponto' => $caixa->ponto,
            'aberta_as' => $caixa->created_at?->format('H:i'),
            'fundo_maneio' => (float) $caixa->fundo_maneio,
            ...app(CaixaService::class)->resumo($caixa),
        ] : null;
    }

    public function leitura(): RedirectResponse
    {
        $caixa = CaixaDiaria::abertaParaPonto(self::pontoDoPosto());
        abort_unless($caixa, 404);

        return $this->imprimir($caixa, 'Leitura da caixa impressa.');
    }

    public function fechar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'valor_contado' => ['required', 'numeric', 'min:0'],
            'contagem' => ['nullable', 'array'],
            'contagem.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'observacoes_fecho' => ['nullable', 'string', 'max:1000'],
            'pin' => ['required', 'string'],
        ], [
            'valor_contado.required' => 'Conta a gaveta e escreve o valor.',
            'pin.required' => 'Escreve o PIN do posto para fechar.',
        ]);

        if (! PosSession::findOrFail(session('pos_id'))->validarPin($data['pin'])) {
            return back()->withErrors(['pin' => 'PIN do posto errado.']);
        }

        $caixa = CaixaDiaria::abertaParaPonto(self::pontoDoPosto());
        if (! $caixa) {
            return back()->withErrors(['valor_contado' => 'A caixa deste ponto ja esta fechada.']);
        }

        $caixa = $this->caixas->fechar(
            $caixa,
            (float) $data['valor_contado'],
            $data['contagem'] ?? [],
            $data['observacoes_fecho'] ?? null,
            null,
            session('pos_operador') ?: session('pos_nome'),
        );

        $diferenca = (float) $caixa->diferenca;
        $resultado = $diferenca == 0 ? 'Bate certo.' : ($diferenca > 0 ? 'Sobram ' : 'Faltam ').number_format(abs($diferenca), 2, ',', ' ').' €.';

        return $this->imprimir($caixa, 'Caixa fechada. '.$resultado);
    }

    public function talao(CaixaDiaria $caixa): Response
    {
        abort_unless($caixa->ponto === self::pontoDoPosto(), 404);

        return Inertia::render('Caixa/Talao', [
            'payload' => $this->caixas->payloadTalao($caixa),
            'modo' => $this->impressoraDoTerminal()?->tipo ?? 'navegador',
            'voltar' => session('pos_tipo') === 'restaurante' ? route('pos.rest.mesas') : null,
        ]);
    }

    private function impressoraDoTerminal(): ?\App\Models\Impressora
    {
        $impressora = PosSession::find(session('pos_id'))?->impressora;

        return $impressora?->ativa ? $impressora : null;
    }

    /**
     * Agente: sai sozinho na impressora do posto. Bar sem agente: o proprio
     * ecra de venda imprime (como as senhas). Restaurante: abre o talao.
     */
    private function imprimir(CaixaDiaria $caixa, string $mensagem): RedirectResponse
    {
        if ($this->caixas->imprimirTalao($caixa, $this->impressoraDoTerminal()?->id)) {
            return back()->with('success', $mensagem);
        }

        $url = route(session('pos_tipo') === 'restaurante' ? 'pos.rest.caixa.talao' : 'pos.caixa.talao', $caixa);

        if (session('pos_tipo') === 'restaurante') {
            return back()->with('success', $mensagem)->with('talao_caixa', $url);
        }

        return back()->with('success', $mensagem)->with('imprimir', [
            'pedido_id' => 'caixa-'.$caixa->id.'-'.now()->timestamp,
            'modo' => $this->impressoraDoTerminal()?->tipo === 'webusb' ? 'webusb' : 'navegador',
            'url' => $url,
            'escpos' => [$this->caixas->payloadTalao($caixa)],
        ]);
    }
}
