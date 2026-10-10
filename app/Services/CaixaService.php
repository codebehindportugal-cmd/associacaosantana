<?php

namespace App\Services;

use App\Models\CaixaDiaria;
use App\Models\CaucaoDevolucao;
use App\Models\Pedido;
use App\Models\PosSession;
use App\Models\PrintJob;
use App\Models\TalaoConfig;
use Illuminate\Support\Facades\DB;

/**
 * Contas da caixa de um ponto (vendas, caucoes, esperado na gaveta), o fecho
 * e o talao de fecho/leitura. Usado pelo backoffice e pelo POS do bar.
 */
class CaixaService
{
    /** Notas e moedas aceites na contagem (chaves do JSON). */
    public const DENOMINACOES = ['500', '200', '100', '50', '20', '10', '5', '2', '1', '0.5', '0.2', '0.1', '0.05', '0.02', '0.01'];

    /**
     * Impressora do ponto: a do posto POS desse ponto ou, se nao tiver, a da
     * seccao (contas no restaurante, bar nos cafes).
     */
    public function impressoraDoPonto(CaixaDiaria $caixa): ?\App\Models\Impressora
    {
        $posto = PosSession::where('ativo', true)
            ->whereNotNull('impressora_id')
            ->where(fn ($q) => $q->where('localizacao', $caixa->ponto)->orWhere('nome', $caixa->ponto))
            ->with('impressora')
            ->first();

        return $posto?->impressora?->ativa ? $posto->impressora : null;
    }

    public function anuladasDoPonto(CaixaDiaria $caixa): array
    {
        if ($caixa->ponto === 'Restaurante') {
            $query = Pedido::where('tipo', 'restaurante');
        } else {
            $query = Pedido::whereIn('tipo', ['bar_conta', 'bar_prepago'])->where('ponto_bar', $caixa->ponto);
        }

        $linha = $query->where('estado', 'cancelado')
            ->where('created_at', '>=', $caixa->created_at)
            ->when($caixa->fechado_at, fn ($q) => $q->where('created_at', '<=', $caixa->fechado_at))
            ->selectRaw('COUNT(*) as n, SUM(COALESCE(valor_devolvido, 0)) as devolvido, SUM(COALESCE(total, 0)) as valor')
            ->first();

        return [
            'quantidade' => (int) ($linha->n ?? 0),
            // Bar: o que se devolveu ao cliente. Restaurante: as contas nao chegam a ser pagas, conta o valor anulado.
            'devolvido' => round((float) ($linha->devolvido ?? 0), 2),
            'valor' => round((float) ($linha->valor ?? 0), 2),
        ];
    }

    /** Linhas do talao de fecho/leitura, no formato do PrintJobService. */
    public function payloadTalao(CaixaDiaria $caixa): array
    {
        $caixa->loadMissing('user', 'fechadoPor');
        $venda = $this->vendasDoPonto($caixa);
        $caucao = $this->caucoesDoPonto($caixa);
        $anuladas = $this->anuladasDoPonto($caixa);
        $esperado = $this->esperadoNaGaveta($caixa, $venda, $caucao);
        $fechada = $caixa->estado === 'fechada';

        $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' EUR';
        $linha = fn (string $rotulo, string $valor) => $rotulo.str_repeat(' ', max(1, 32 - mb_strlen($rotulo) - mb_strlen($valor))).$valor;
        $sep = str_repeat('-', 32);
        $grande = fn (string $texto) => ['texto' => $texto, 'alinhamento' => 'centro', 'tamanho' => 'grande'];

        $metodos = [
            'Dinheiro' => (float) ($venda->entrada_gaveta ?? 0),
            'MB WAY' => (float) ($venda->mbway ?? 0),
            'Contactless' => (float) ($venda->contactless ?? 0),
            'Multibanco' => (float) ($venda->multibanco ?? 0),
        ];

        $linhas = [
            ...TalaoConfig::atual()->linhasCabecalho(),
            'Ponto: '.$caixa->ponto,
            'Dia: '.$caixa->data->format('d/m/Y'),
            'Aberta: '.$caixa->created_at->format('d/m H:i').($caixa->user ? ' ('.$caixa->user->name.')' : ''),
            $fechada
                ? 'Fechada: '.$caixa->fechado_at?->format('d/m H:i').(($caixa->fechadoPor?->name ?? $caixa->fechado_por_nome) ? ' ('.($caixa->fechadoPor?->name ?? $caixa->fechado_por_nome).')' : '')
                : 'Leitura: '.now()->format('d/m H:i').' (caixa aberta)',
            $sep,
            $linha('Vendas', $eur($venda->total ?? 0)),
            $linha($caixa->ponto === 'Restaurante' ? 'Contas' : 'Senhas', (string) (int) ($venda->pedidos ?? 0)),
            ...((float) ($venda->doacoes ?? 0) > 0 ? [$linha('Doacoes (troco)', $eur($venda->doacoes))] : []),
            $sep,
            'RECEBIDO POR FORMA DE PAGAMENTO',
            ...collect($metodos)->filter(fn ($v, $k) => $k === 'Dinheiro' || $k === 'MB WAY' || $v != 0)
                ->map(fn ($v, $k) => $linha($k, $eur($v)))->values()->all(),
            $linha('Total recebido', $eur(array_sum($metodos))),
        ];

        if ($caucao['recebidas'] > 0 || $caucao['bebidas'] > 0 || $caucao['dinheiro'] > 0) {
            array_push($linhas,
                $sep,
                'CAUCOES',
                $linha('Recebidas', $eur($caucao['recebidas'])),
                $linha('Trocadas bebidas', '-'.$eur($caucao['bebidas'])),
                $linha('Devolvidas dinheiro', '-'.$eur($caucao['dinheiro'])),
            );
        }

        if ($anuladas['quantidade'] > 0) {
            $restaurante = $caixa->ponto === 'Restaurante';
            array_push($linhas,
                $sep,
                $linha($restaurante ? 'Contas anuladas' : 'Senhas anuladas', (string) $anuladas['quantidade']),
                $restaurante
                    ? $linha('Valor anulado', $eur($anuladas['valor']))
                    : $linha('Devolvido', $eur($anuladas['devolvido'])),
            );
        }

        array_push($linhas,
            $sep,
            'GAVETA (DINHEIRO)',
            $linha('Fundo de maneio', $eur($caixa->fundo_maneio)),
            $linha('Esperado', $eur($esperado)),
        );

        if ($fechada) {
            $diferenca = (float) $caixa->diferenca;
            array_push($linhas,
                $linha('Contado', $eur($caixa->valor_contado)),
                $grande(($diferenca == 0 ? 'CERTO' : ($diferenca > 0 ? 'SOBRA ' : 'FALTA ')).($diferenca == 0 ? '' : $eur(abs($diferenca)))),
                $linha('Entregar s/ fundo', $eur((float) $caixa->valor_contado - (float) $caixa->fundo_maneio)),
            );

            if ($caixa->contagem) {
                $linhas[] = $sep;
                $linhas[] = 'CONTAGEM';
                foreach (self::DENOMINACOES as $valor) {
                    $qtd = (int) ($caixa->contagem[$valor] ?? 0);
                    if ($qtd > 0) {
                        $linhas[] = $linha(sprintf('%d x %s', $qtd, number_format((float) $valor, 2, ',', '')), $eur($qtd * (float) $valor));
                    }
                }
            }

            if ($caixa->observacoes_fecho) {
                array_push($linhas, $sep, 'Obs: '.$caixa->observacoes_fecho);
            }

        }

        $vendidos = $this->produtosVendidos($caixa);
        if ($vendidos->isNotEmpty()) {
            $linhas[] = $sep;
            $linhas[] = 'PRODUTOS VENDIDOS';
            foreach ($vendidos as $p) {
                $qtd = rtrim(rtrim(number_format((float) $p->quantidade, 3, ',', ''), '0'), ',');
                $linhas[] = mb_strimwidth($qtd.' x '.$p->nome, 0, 32);
                if ($p->gerir_stock) {
                    $resta = rtrim(rtrim(number_format((float) $p->stock_atual, 3, ',', ''), '0'), ',');
                    $linhas[] = '   '.((float) $p->stock_atual <= 0 ? 'ESGOTADO' : 'stock: '.$resta);
                }
            }
        }

        if ($fechada) {
            array_push($linhas, '', '', 'Entregue por: ______________', '', 'Recebido por: ______________');
        }

        return [
            'titulo' => TalaoConfig::atual()->tituloImpresso(),
            'subtitulo' => $fechada ? 'FECHO DE CAIXA' : 'LEITURA DE CAIXA',
            'linhas' => $linhas,
            'cortar' => true,
        ];
    }

    /**
     * Dinheiro que tem de estar na gaveta: fundo + o que entrou a dinheiro
     * (vendas, caucoes cobradas menos as descontadas, troco deixado) menos as
     * caucoes devolvidas em dinheiro. MB WAY, contactless e multibanco nao
     * entram na gaveta.
     */
    public function esperadoNaGaveta(CaixaDiaria $caixa, ?object $venda, array $caucao): float
    {
        return round((float) $caixa->fundo_maneio + (float) ($venda?->entrada_gaveta ?? 0) - (float) $caucao['dinheiro'], 2);
    }

    private function somasVenda(): array
    {
        $metodo = "COALESCE(metodo_pagamento, 'dinheiro')";
        $pago = 'total + COALESCE(caucao_cobrada, 0) - COALESCE(caucao_descontada, 0) + COALESCE(doacao, 0)';

        return [
            DB::raw('SUM(total) as total'),
            DB::raw('SUM(doacao) as doacoes'),
            DB::raw('COUNT(*) as pedidos'),
            DB::raw("SUM(CASE WHEN $metodo = 'dinheiro' THEN $pago ELSE 0 END) as entrada_gaveta"),
            DB::raw("SUM(CASE WHEN $metodo = 'mbway' THEN $pago ELSE 0 END) as mbway"),
            DB::raw("SUM(CASE WHEN $metodo = 'contactless' THEN $pago ELSE 0 END) as contactless"),
            DB::raw("SUM(CASE WHEN $metodo = 'multibanco' THEN $pago ELSE 0 END) as multibanco"),
        ];
    }

    public function vendasDoPonto(CaixaDiaria $caixa): object
    {
        if ($caixa->ponto === 'Restaurante') {
            return Pedido::where('tipo', 'restaurante')
                ->where('created_at', '>=', $caixa->created_at)
                ->when($caixa->fechado_at, fn ($query) => $query->where('created_at', '<=', $caixa->fechado_at))
                ->where('estado', 'entregue')
                ->select($this->somasVenda())
                ->first();
        }

        return Pedido::whereIn('tipo', ['bar_conta', 'bar_prepago'])
            ->where('created_at', '>=', $caixa->created_at)
            ->when($caixa->fechado_at, fn ($query) => $query->where('created_at', '<=', $caixa->fechado_at))
            ->where('ponto_bar', $caixa->ponto)
            ->where(fn ($query) => $query->where('estado', 'entregue')->orWhere('pago_antecipado', true))
            ->select($this->somasVenda())
            ->first();
    }

    /**
     * Caucoes dos metros neste ponto. Nao sao vendas: entram e saem da gaveta.
     * recebidas  = caucao cobrada nas senhas
     * bebidas    = metros devolvidos descontados numa senha (dinheiro que nao entrou)
     * dinheiro   = metros devolvidos com o dinheiro devolvido (saiu da gaveta)
     * saldo      = efeito na gaveta (um metro pode ser comprado num ponto e devolvido noutro)
     */
    public function caucoesDoPonto(CaixaDiaria $caixa): array
    {
        $vazio = ['recebidas' => 0.0, 'bebidas' => 0.0, 'dinheiro' => 0.0, 'saldo' => 0.0];

        if ($caixa->ponto === 'Restaurante') {
            return $vazio;
        }

        $recebidas = (float) Pedido::whereIn('tipo', ['bar_conta', 'bar_prepago'])
            ->where('created_at', '>=', $caixa->created_at)
            ->when($caixa->fechado_at, fn ($query) => $query->where('created_at', '<=', $caixa->fechado_at))
            ->where('ponto_bar', $caixa->ponto)
            ->where(fn ($query) => $query->where('estado', 'entregue')->orWhere('pago_antecipado', true))
            ->sum('caucao_cobrada');

        $devolucoes = CaucaoDevolucao::where('ponto', $caixa->ponto)
            ->where('created_at', '>=', $caixa->created_at)
            ->when($caixa->fechado_at, fn ($query) => $query->where('created_at', '<=', $caixa->fechado_at))
            ->selectRaw('modo, SUM(valor_total) as total')
            ->groupBy('modo')
            ->pluck('total', 'modo');

        $bebidas = (float) ($devolucoes['bebidas'] ?? 0);
        $dinheiro = (float) ($devolucoes['dinheiro'] ?? 0);

        return [
            'recebidas' => round($recebidas, 2),
            'bebidas' => round($bebidas, 2),
            'dinheiro' => round($dinheiro, 2),
            'saldo' => round($recebidas - $bebidas - $dinheiro, 2),
        ];
    }

    /**
     * Quantidades vendidas por produto nesta caixa (senhas/contas que contam
     * para as vendas) e o stock atual dos que gerem stock.
     */
    public function produtosVendidos(CaixaDiaria $caixa)
    {
        $query = DB::table('pedido_items')
            ->join('pedidos', 'pedido_items.pedido_id', '=', 'pedidos.id')
            ->join('produtos', 'pedido_items.produto_id', '=', 'produtos.id')
            ->where('pedidos.created_at', '>=', $caixa->created_at)
            ->when($caixa->fechado_at, fn ($q) => $q->where('pedidos.created_at', '<=', $caixa->fechado_at));

        if ($caixa->ponto === 'Restaurante') {
            $query->where('pedidos.tipo', 'restaurante')->where('pedidos.estado', 'entregue');
        } else {
            $query->whereIn('pedidos.tipo', ['bar_conta', 'bar_prepago'])
                ->where('pedidos.ponto_bar', $caixa->ponto)
                ->where(fn ($q) => $q->where('pedidos.estado', 'entregue')->orWhere('pedidos.pago_antecipado', true));
        }

        return $query->groupBy('produtos.id', 'produtos.nome', 'produtos.gerir_stock', 'produtos.stock_atual')
            ->select('produtos.nome', 'produtos.gerir_stock', 'produtos.stock_atual', DB::raw('SUM(pedido_items.quantidade) as quantidade'))
            ->orderByDesc('quantidade')
            ->orderBy('produtos.nome')
            ->get();
    }

    /**
     * Fecha a caixa com o valor contado. $fechadoPor: utilizador do backoffice
     * ou, no POS, o nome do operador.
     */
    public function fechar(CaixaDiaria $caixa, float $valorContado, array $contagem = [], ?string $observacoes = null, ?int $userId = null, ?string $fechadoPorNome = null): CaixaDiaria
    {
        $esperado = $this->esperadoNaGaveta($caixa, $this->vendasDoPonto($caixa), $this->caucoesDoPonto($caixa));
        $valorContado = round($valorContado, 2);
        $contagem = collect($contagem)
            ->filter(fn ($qtd, $valor) => in_array((string) $valor, self::DENOMINACOES, true) && (int) $qtd > 0)
            ->map(fn ($qtd) => (int) $qtd)
            ->all();

        $caixa->update([
            'estado' => 'fechada',
            'valor_contado' => $valorContado,
            'contagem' => $contagem ?: null,
            'diferenca' => round($valorContado - $esperado, 2),
            'observacoes_fecho' => $observacoes,
            'fechado_user_id' => $userId,
            'fechado_por_nome' => $fechadoPorNome,
            'fechado_at' => now(),
        ]);

        return $caixa->fresh();
    }

    /** Envia o talao para a impressora do agente; devolve o trabalho ou null (imprimir no browser). */
    public function imprimirTalao(CaixaDiaria $caixa, ?int $impressoraId = null): ?PrintJob
    {
        return app(PrintJobService::class)
            ->paraImpressora($impressoraId ?: $this->impressoraDoPonto($caixa)?->id)
            ->criarTalaoCaixa($caixa, $this->payloadTalao($caixa), $caixa->ponto === 'Restaurante' ? 'contas' : 'bar');
    }

    /** Resumo para mostrar no ecra (backoffice e POS). */
    public function resumo(CaixaDiaria $caixa): array
    {
        $venda = $this->vendasDoPonto($caixa);
        $caucao = $this->caucoesDoPonto($caixa);

        return [
            'vendas' => (float) ($venda->total ?? 0),
            'doacoes' => (float) ($venda->doacoes ?? 0),
            'por_metodo' => [
                'dinheiro' => round((float) ($venda->entrada_gaveta ?? 0), 2),
                'mbway' => round((float) ($venda->mbway ?? 0), 2),
                'contactless' => round((float) ($venda->contactless ?? 0), 2),
                'multibanco' => round((float) ($venda->multibanco ?? 0), 2),
            ],
            'pedidos' => (int) ($venda->pedidos ?? 0),
            'esperado_caixa' => $this->esperadoNaGaveta($caixa, $venda, $caucao),
            'caucao' => $caucao,
            'anuladas' => $this->anuladasDoPonto($caixa),
        ];
    }
}
