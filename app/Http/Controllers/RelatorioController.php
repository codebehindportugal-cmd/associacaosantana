<?php

namespace App\Http\Controllers;

use App\Models\CaixaDiaria;
use App\Models\FaturaCompra;
use App\Models\FestaMovimento;
use App\Models\Pedido;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RelatorioController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:relatorios.ver');
    }

    public function index(): Response
    {
        $sessao = now()->subHours(12);
        $pedidos = Pedido::with('items.produto')
            ->whereRaw('DATE(DATE_SUB(created_at, INTERVAL 12 HOUR)) = ?', [$sessao->toDateString()])
            ->where(fn ($q) => $q->where('estado', 'entregue')->orWhere('pago_antecipado', true))
            ->get();
        $total = (float) $pedidos->sum('total');
        $margem = $this->margemResumo($sessao, $sessao);

        return Inertia::render('Relatorios/Index', [
            'resumo' => [
                'total_vendas_hoje' => $total,
                'custo_estimado_hoje' => $margem['custo_estimado'],
                'margem_estimada_hoje' => $margem['margem_estimada'],
                'margem_percentagem_hoje' => $margem['margem_percentagem'],
                'total_pedidos_hoje' => $pedidos->count(),
                'media_por_pedido' => $pedidos->count() ? $total / $pedidos->count() : 0,
                'vendas_restaurante_hoje' => (float) $pedidos->where('tipo', 'restaurante')->sum('total'),
                'vendas_bar_hoje' => (float) $pedidos->where('tipo', 'bar_conta')->sum('total'),
                'vendas_prepago_hoje' => (float) $pedidos->where('tipo', 'bar_prepago')->sum('total'),
                'doacoes_hoje' => (float) $pedidos->sum('doacao'),
            ],
            'vendas_bar_por_ponto' => $this->barPorPonto($pedidos),
            'caixas_por_ponto' => $this->caixasPorPonto(today(), today(), $pedidos),
            'top_produtos_hoje' => $this->topProdutos(today(), today(), 5),
        ]);
    }

    public function porPeriodo(Request $request): Response
    {
        $inicio = $request->date('data_inicio') ?? today();
        $fim = $request->date('data_fim') ?? today();
        $tipo = $request->input('tipo', 'todos');
        $query = Pedido::whereBetween(DB::raw('DATE(DATE_SUB(created_at, INTERVAL 12 HOUR))'), [$inicio->toDateString(), $fim->toDateString()])->where(fn ($q) => $q->where('estado', 'entregue')->orWhere('pago_antecipado', true));
        if ($tipo !== 'todos') {
            $query->where('tipo', $tipo === 'bar' ? 'bar_conta' : $tipo);
        }
        $pedidos = $query->get();
        $total = (float) $pedidos->sum('total');
        $dias = max(1, $inicio->diffInDays($fim) + 1);
        $margem = $this->margemResumo($inicio, $fim, $tipo);

        return Inertia::render('Relatorios/PorPeriodo', [
            'filters' => ['data_inicio' => $inicio->toDateString(), 'data_fim' => $fim->toDateString(), 'tipo' => $tipo],
            'resumo' => [
                'total_periodo' => $total,
                'custo_estimado' => $margem['custo_estimado'],
                'margem_estimada' => $margem['margem_estimada'],
                'margem_percentagem' => $margem['margem_percentagem'],
                'total_pedidos' => $pedidos->count(),
                'media_diaria' => $total / $dias,
                'total_doacoes' => (float) $pedidos->sum('doacao'),
                'receitas_festa' => (float) FestaMovimento::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])->where('tipo', 'receita')->sum('valor'),
                'custos_festa' => (float) FestaMovimento::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])->where('tipo', 'custo')->sum('valor'),
                'custos_stock' => (float) FaturaCompra::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])->sum('total'),
                'lucro_liquido' => $total
                    + (float) FestaMovimento::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])->where('tipo', 'receita')->sum('valor')
                    - (float) FestaMovimento::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])->where('tipo', 'custo')->sum('valor')
                    - (float) FaturaCompra::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])->sum('total'),
            ],
            'vendas_por_dia' => $pedidos->groupBy(fn ($p) => $p->created_at->subHours(12)->toDateString())->map(fn ($g, $d) => ['data' => $d, 'total' => (float) $g->sum('total')])->values(),
            'vendas_por_tipo' => $pedidos->groupBy('tipo')->map(fn ($g, $t) => ['tipo' => $t, 'total' => (float) $g->sum('total'), 'percentagem' => $total ? ((float) $g->sum('total') / $total) * 100 : 0])->values(),
            'vendas_bar_por_ponto' => $this->barPorPonto($pedidos),
            'caixas_por_ponto' => $this->caixasPorPonto($inicio, $fim, $pedidos),
            'top_produtos' => $this->topProdutos($inicio, $fim, 10, $tipo),
            'todos_produtos' => $this->topProdutos($inicio, $fim, 500, $tipo),
            'top_categorias' => $this->topCategorias($inicio, $fim),
            'vendas_por_hora' => $this->vendasPorHora($inicio, $fim, $tipo),
            'vendas_por_secao' => $this->vendasPorSecao($inicio, $fim),
            'metodos_pagamento' => $this->vendasPorMetodoPagamento($inicio, $fim),
            'festa_receitas' => $this->festaReceitas($inicio, $fim, $pedidos),
            'festa_custos' => $this->festaCustos($inicio, $fim),
        ]);
    }

    public function exportarPDF(Request $request)
    {
        $inicio = $request->date('data_inicio') ?? today();
        $fim = $request->date('data_fim') ?? today();
        $pedidos = Pedido::whereBetween(DB::raw('DATE(DATE_SUB(created_at, INTERVAL 12 HOUR))'), [$inicio->toDateString(), $fim->toDateString()])->where(fn ($q) => $q->where('estado', 'entregue')->orWhere('pago_antecipado', true))->get();
        $dados = [
            'inicio' => $inicio,
            'fim' => $fim,
            'total' => (float) $pedidos->sum('total'),
            'total_pedidos' => $pedidos->count(),
            'vendas_por_dia' => $pedidos->groupBy(fn ($p) => $p->created_at->subHours(12)->toDateString())->map(fn ($g, $d) => ['data' => $d, 'total' => (float) $g->sum('total')])->values(),
            'vendas_por_tipo' => $pedidos->groupBy('tipo')->map(fn ($g, $t) => ['tipo' => $t, 'total' => (float) $g->sum('total')])->values(),
            'vendas_bar_por_ponto' => $this->barPorPonto($pedidos),
            'caixas_por_ponto' => $this->caixasPorPonto($inicio, $fim, $pedidos),
            'top_produtos' => $this->topProdutos($inicio, $fim, 10),
        ];

        return Pdf::loadView('pdf.relatorio-periodo', $dados)->download('relatorio-vendas.pdf');
    }

    private function topProdutos($inicio, $fim, int $limite, string $tipo = 'todos')
    {
        $custosReceita = $this->custosReceitaSubquery();

        $query = DB::table('pedido_items')
            ->join('pedidos', 'pedido_items.pedido_id', '=', 'pedidos.id')
            ->join('produtos', 'pedido_items.produto_id', '=', 'produtos.id')
            ->leftJoin('categorias', 'produtos.categoria_id', '=', 'categorias.id')
            ->leftJoinSub($custosReceita, 'custos_receita', fn ($join) => $join->on('custos_receita.produto_id', '=', 'produtos.id'))
            ->where(fn ($q) => $q->where('pedidos.estado', 'entregue')->orWhere('pedidos.pago_antecipado', true))
            ->whereBetween(DB::raw('DATE(DATE_SUB(pedidos.created_at, INTERVAL 12 HOUR))'), [$inicio->toDateString(), $fim->toDateString()]);

        if ($tipo !== 'todos') {
            $query->where('pedidos.tipo', $tipo === 'bar' ? 'bar_conta' : $tipo);
        }

        return $query
            ->groupBy('produtos.id', 'produtos.nome', 'categorias.nome', 'produtos.custo_compra_unitario', 'produtos.custo_preparacao_unitario', 'custos_receita.custo_componentes')
            ->select(
                'produtos.nome',
                'categorias.nome as categoria',
                DB::raw('SUM(pedido_items.quantidade) as quantidade'),
                DB::raw('SUM(pedido_items.quantidade * pedido_items.preco_unitario) as total'),
                DB::raw('SUM(pedido_items.quantidade * (CASE WHEN custos_receita.custo_componentes IS NULL THEN produtos.custo_compra_unitario ELSE custos_receita.custo_componentes END + produtos.custo_preparacao_unitario)) as custo_estimado'),
                DB::raw('SUM(pedido_items.quantidade * pedido_items.preco_unitario) - SUM(pedido_items.quantidade * (CASE WHEN custos_receita.custo_componentes IS NULL THEN produtos.custo_compra_unitario ELSE custos_receita.custo_componentes END + produtos.custo_preparacao_unitario)) as margem_estimada'),
                DB::raw('CASE WHEN SUM(pedido_items.quantidade * pedido_items.preco_unitario) > 0 THEN ((SUM(pedido_items.quantidade * pedido_items.preco_unitario) - SUM(pedido_items.quantidade * (CASE WHEN custos_receita.custo_componentes IS NULL THEN produtos.custo_compra_unitario ELSE custos_receita.custo_componentes END + produtos.custo_preparacao_unitario))) / SUM(pedido_items.quantidade * pedido_items.preco_unitario)) * 100 ELSE 0 END as margem_percentagem')
            )
            ->orderByDesc('quantidade')
            ->limit($limite)
            ->get();
    }

    private function margemResumo($inicio, $fim, string $tipo = 'todos'): array
    {
        $custosReceita = $this->custosReceitaSubquery();
        $query = DB::table('pedido_items')
            ->join('pedidos', 'pedido_items.pedido_id', '=', 'pedidos.id')
            ->join('produtos', 'pedido_items.produto_id', '=', 'produtos.id')
            ->leftJoinSub($custosReceita, 'custos_receita', fn ($join) => $join->on('custos_receita.produto_id', '=', 'produtos.id'))
            ->where(fn ($q) => $q->where('pedidos.estado', 'entregue')->orWhere('pedidos.pago_antecipado', true))
            ->whereBetween(DB::raw('DATE(DATE_SUB(pedidos.created_at, INTERVAL 12 HOUR))'), [$inicio->toDateString(), $fim->toDateString()]);

        if ($tipo !== 'todos') {
            $query->where('pedidos.tipo', $tipo === 'bar' ? 'bar_conta' : $tipo);
        }

        $linha = $query->selectRaw('
            SUM(pedido_items.quantidade * pedido_items.preco_unitario) as total,
            SUM(pedido_items.quantidade * (CASE WHEN custos_receita.custo_componentes IS NULL THEN produtos.custo_compra_unitario ELSE custos_receita.custo_componentes END + produtos.custo_preparacao_unitario)) as custo_estimado
        ')
            ->first();

        $total = (float) ($linha->total ?? 0);
        $custo = (float) ($linha->custo_estimado ?? 0);
        $margem = $total - $custo;

        return [
            'custo_estimado' => $custo,
            'margem_estimada' => $margem,
            'margem_percentagem' => $total > 0 ? ($margem / $total) * 100 : 0,
        ];
    }

    private function custosReceitaSubquery()
    {
        return DB::table('produto_componentes')
            ->join('produtos as componentes', 'produto_componentes.componente_id', '=', 'componentes.id')
            ->groupBy('produto_componentes.produto_id')
            ->select('produto_componentes.produto_id', DB::raw('SUM(produto_componentes.quantidade * componentes.custo_compra_unitario) as custo_componentes'));
    }

    private function barPorPonto($pedidos)
    {
        $bar = $pedidos->whereIn('tipo', ['bar_conta', 'bar_prepago']);
        $totalBar = (float) $bar->sum('total');

        return $bar
            ->groupBy(fn ($pedido) => $pedido->ponto_bar ?: 'Sem ponto definido')
            ->map(fn ($grupo, $ponto) => [
                'ponto' => $ponto,
                'total' => (float) $grupo->sum('total'),
                'pedidos' => $grupo->count(),
                'percentagem' => $totalBar ? ((float) $grupo->sum('total') / $totalBar) * 100 : 0,
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function caixasPorPonto($inicio, $fim, $pedidos)
    {
        $vendas = $this->barPorPonto($pedidos)->keyBy('ponto');
        $vendas->put('Restaurante', [
            'ponto' => 'Restaurante',
            'total' => (float) $pedidos->where('tipo', 'restaurante')->sum('total'),
            'pedidos' => $pedidos->where('tipo', 'restaurante')->count(),
            'percentagem' => 0,
        ]);

        return CaixaDiaria::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->get()
            ->groupBy('ponto')
            ->map(function ($grupo, $ponto) use ($vendas) {
                $venda = $vendas->get($ponto);
                $fundo = (float) $grupo->sum('fundo_maneio');
                $totalVendas = (float) ($venda['total'] ?? 0);
                $totalContado = (float) $grupo->sum('valor_contado');

                return [
                    'ponto' => $ponto,
                    'fundo_maneio' => $fundo,
                    'vendas' => $totalVendas,
                    'esperado_caixa' => $fundo + $totalVendas,
                    'valor_contado' => $totalContado,
                    'diferenca' => $totalContado ? $totalContado - ($fundo + $totalVendas) : (float) $grupo->sum('diferenca'),
                    'dias_abertos' => $grupo->count(),
                    'dias_fechados' => $grupo->where('estado', 'fechada')->count(),
                ];
            })
            ->sortBy('ponto')
            ->values();
    }

    private function vendasPorHora($inicio, $fim, string $tipo = 'todos')
    {
        $query = DB::table('pedidos')
            ->where(fn ($q) => $q->where('estado', 'entregue')->orWhere('pago_antecipado', true))
            ->whereBetween(DB::raw('DATE(DATE_SUB(created_at, INTERVAL 12 HOUR))'), [$inicio->toDateString(), $fim->toDateString()]);

        if ($tipo !== 'todos') {
            $query->where('tipo', $tipo === 'bar' ? 'bar_conta' : $tipo);
        }

        return $query
            ->selectRaw('HOUR(created_at) as hora, COUNT(*) as pedidos, SUM(total) as total')
            ->groupByRaw('HOUR(created_at)')
            ->orderByRaw('HOUR(created_at)')
            ->get();
    }

    private function vendasPorSecao($inicio, $fim)
    {
        return DB::table('pedido_items')
            ->join('pedidos', 'pedido_items.pedido_id', '=', 'pedidos.id')
            ->where(fn ($q) => $q->where('pedidos.estado', 'entregue')->orWhere('pedidos.pago_antecipado', true))
            ->whereBetween(DB::raw('DATE(DATE_SUB(pedidos.created_at, INTERVAL 12 HOUR))'), [$inicio->toDateString(), $fim->toDateString()])
            ->selectRaw('COALESCE(pedido_items.secao, "sem secção") as secao, SUM(pedido_items.quantidade) as quantidade, SUM(pedido_items.quantidade * pedido_items.preco_unitario) as total')
            ->groupBy('pedido_items.secao')
            ->orderByDesc('total')
            ->get();
    }

    private function vendasPorMetodoPagamento($inicio, $fim)
    {
        return DB::table('pedidos')
            ->where(fn ($q) => $q->where('estado', 'entregue')->orWhere('pago_antecipado', true))
            ->whereBetween(DB::raw('DATE(DATE_SUB(created_at, INTERVAL 12 HOUR))'), [$inicio->toDateString(), $fim->toDateString()])
            ->selectRaw('COALESCE(metodo_pagamento, "dinheiro") as metodo, COUNT(*) as pedidos, SUM(total) as total')
            ->groupBy('metodo_pagamento')
            ->orderByDesc('total')
            ->get();
    }

    private function topCategorias($inicio, $fim)
    {
        return DB::table('pedido_items')
            ->join('pedidos', 'pedido_items.pedido_id', '=', 'pedidos.id')
            ->join('produtos', 'pedido_items.produto_id', '=', 'produtos.id')
            ->leftJoin('categorias', 'produtos.categoria_id', '=', 'categorias.id')
            ->where(fn ($q) => $q->where('pedidos.estado', 'entregue')->orWhere('pedidos.pago_antecipado', true))
            ->whereBetween(DB::raw('DATE(DATE_SUB(pedidos.created_at, INTERVAL 12 HOUR))'), [$inicio->toDateString(), $fim->toDateString()])
            ->groupBy('categorias.nome')
            ->select('categorias.nome as categoria', DB::raw('SUM(pedido_items.quantidade * pedido_items.preco_unitario) as total'))
            ->orderByDesc('total')
            ->get();
    }

    private function festaReceitas($inicio, $fim, $pedidos): array
    {
        $automaticas = [
            ['label' => 'Restaurante', 'valor' => (float) $pedidos->where('tipo', 'restaurante')->sum('total')],
            ['label' => 'Bar', 'valor' => (float) $pedidos->whereIn('tipo', ['bar_conta', 'bar_prepago'])->sum('total')],
            ['label' => 'Doações', 'valor' => (float) $pedidos->sum('doacao')],
        ];

        $manuais = FestaMovimento::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->where('tipo', 'receita')
            ->get()
            ->groupBy('categoria')
            ->map(fn ($grupo, $cat) => [
                'label' => ucfirst(str_replace('_', ' ', $cat)),
                'valor' => (float) $grupo->sum('valor'),
            ])
            ->values()
            ->all();

        return array_values(array_filter(
            array_merge($automaticas, $manuais),
            fn ($r) => $r['valor'] > 0
        ));
    }

    private function festaCustos($inicio, $fim): array
    {
        $stock = [
            ['label' => 'Compras de stock', 'valor' => (float) FaturaCompra::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])->sum('total')],
        ];

        $manuais = FestaMovimento::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->where('tipo', 'custo')
            ->get()
            ->groupBy('categoria')
            ->map(fn ($grupo, $cat) => [
                'label' => ucfirst(str_replace('_', ' ', $cat)),
                'valor' => (float) $grupo->sum('valor'),
            ])
            ->values()
            ->all();

        return array_values(array_filter(
            array_merge($stock, $manuais),
            fn ($c) => $c['valor'] > 0
        ));
    }
}
