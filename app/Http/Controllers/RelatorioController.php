<?php

namespace App\Http\Controllers;

use App\Models\CaixaDiaria;
use App\Models\FaturaCompra;
use App\Models\FestaMovimento;
use App\Models\Pedido;
use App\Models\Produto;
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
                'receitas_festa' => (float) FestaMovimento::where(fn ($q) => $q->whereNull('data')->orWhereBetween('data', [$inicio->toDateString(), $fim->toDateString()]))->where('tipo', 'receita')->sum('valor'),
                'custos_festa' => (float) FestaMovimento::where(fn ($q) => $q->whereNull('data')->orWhereBetween('data', [$inicio->toDateString(), $fim->toDateString()]))->where('tipo', 'custo')->sum('valor'),
                'custos_stock' => (float) FaturaCompra::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])->sum('total'),
                'lucro_liquido' => $total
                    + (float) FestaMovimento::where(fn ($q) => $q->whereNull('data')->orWhereBetween('data', [$inicio->toDateString(), $fim->toDateString()]))->where('tipo', 'receita')->sum('valor')
                    - (float) FestaMovimento::where(fn ($q) => $q->whereNull('data')->orWhereBetween('data', [$inicio->toDateString(), $fim->toDateString()]))->where('tipo', 'custo')->sum('valor')
                    - (float) FaturaCompra::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])->sum('total'),
            ],
            'vendas_por_dia' => $pedidos->groupBy(fn ($p) => $p->created_at->subHours(12)->toDateString())->map(fn ($g, $d) => ['data' => $d, 'total' => (float) $g->sum('total')])->values(),
            'vendas_por_tipo' => $pedidos->groupBy('tipo')->map(fn ($g, $t) => ['tipo' => $t, 'total' => (float) $g->sum('total'), 'percentagem' => $total ? ((float) $g->sum('total') / $total) * 100 : 0])->values(),
            'vendas_bar_por_ponto' => $this->barPorPonto($pedidos),
            'caixas_por_ponto' => $this->caixasPorPonto($inicio, $fim, $pedidos),
            'top_produtos' => $this->topProdutos($inicio, $fim, 10, $tipo),
            'todos_produtos' => $this->topProdutos($inicio, $fim, null, $tipo),
            'top_categorias' => $this->topCategorias($inicio, $fim),
            'vendas_por_hora' => $this->vendasPorHora($inicio, $fim, $tipo),
            'vendas_por_secao' => $this->vendasPorSecao($inicio, $fim),
            'metodos_pagamento' => $this->vendasPorMetodoPagamento($inicio, $fim),
            'festa_receitas' => $this->festaReceitas($inicio, $fim, $pedidos),
            'festa_custos' => $this->festaCustos($inicio, $fim),
            'vendas_por_operador' => $this->vendasPorOperador($inicio, $fim, $tipo),
            'stock' => $this->stockPeriodo($inicio, $fim),
        ]);
    }

    /** Colunas da tabela de produtos que se podem exportar (o nome sai sempre). */
    public const COLUNAS_PRODUTOS = [
        'categoria' => 'Categoria',
        'quantidade' => 'Qtd',
        'total' => 'Total',
        'custo' => 'Custo',
        'margem' => 'Margem',
        'margem_pct' => 'Margem %',
    ];

    public const SECCOES_EXPORT = ['resumo', 'dias', 'tipos', 'bar', 'caixa', 'operadores', 'produtos', 'stock'];

    public function exportarPDF(Request $request)
    {
        $opcoes = $request->validate([
            'tipo' => ['nullable', 'in:todos,restaurante,bar,bar_prepago'],
            'formato' => ['nullable', 'in:pdf,csv'],
            'tabela' => ['nullable', 'in:produtos,stock,operadores'],
            'produtos' => ['nullable', 'in:todos,top10'],
            'colunas' => ['nullable', 'array'],
            'colunas.*' => ['in:'.implode(',', array_keys(self::COLUNAS_PRODUTOS))],
            'seccoes' => ['nullable', 'array'],
            'seccoes.*' => ['in:'.implode(',', self::SECCOES_EXPORT)],
        ]);

        $inicio = $request->date('data_inicio') ?? today();
        $fim = $request->date('data_fim') ?? today();
        $tipo = $opcoes['tipo'] ?? 'todos';
        // Sem escolha explicita: todas as colunas, todas as seccoes, todos os produtos
        $colunas = array_values(array_intersect(array_keys(self::COLUNAS_PRODUTOS), $opcoes['colunas'] ?? array_keys(self::COLUNAS_PRODUTOS)));
        $seccoes = $opcoes['seccoes'] ?? self::SECCOES_EXPORT;
        $produtos = $this->topProdutos($inicio, $fim, ($opcoes['produtos'] ?? 'todos') === 'top10' ? 10 : null, $tipo);
        $nomeFicheiro = 'relatorio-vendas-'.$inicio->format('Y-m-d').($fim->ne($inicio) ? '_'.$fim->format('Y-m-d') : '');

        if (($opcoes['formato'] ?? 'pdf') === 'csv') {
            return match ($opcoes['tabela'] ?? 'produtos') {
                'stock' => $this->tabelaCsv(['Produto', 'Categoria', 'Inicial', 'Entradas', 'Vendido', 'Final', 'Atual'],
                    array_map(fn ($l) => [$l['nome'], $l['categoria'], ...array_map(fn ($k) => $this->numeroCsv($l[$k], 3), ['inicial', 'entradas', 'vendido', 'final', 'atual'])], $this->stockPeriodo($inicio, $fim)),
                    'stock-'.$nomeFicheiro.'.csv'),
                'operadores' => $this->tabelaCsv(['Operador', 'Vendas', 'Total', 'Dinheiro', 'MB WAY', 'Outros', 'Anuladas', 'Devolvido'],
                    array_map(fn ($l) => [$l['operador'], $l['pedidos'], ...array_map(fn ($k) => $this->numeroCsv($l[$k]), ['total', 'dinheiro', 'mbway', 'outros']), $l['anuladas'], $this->numeroCsv($l['devolvido'])], $this->vendasPorOperador($inicio, $fim, $tipo)),
                    'operadores-'.$nomeFicheiro.'.csv'),
                default => $this->produtosCsv($produtos, $colunas, $nomeFicheiro.'.csv'),
            };
        }

        $query = Pedido::whereBetween(DB::raw('DATE(DATE_SUB(created_at, INTERVAL 12 HOUR))'), [$inicio->toDateString(), $fim->toDateString()])->where(fn ($q) => $q->where('estado', 'entregue')->orWhere('pago_antecipado', true));
        if ($tipo !== 'todos') {
            $query->where('tipo', $tipo === 'bar' ? 'bar_conta' : $tipo);
        }
        $pedidos = $query->get();

        $dados = [
            'inicio' => $inicio,
            'fim' => $fim,
            'tipo' => ['todos' => 'Todos', 'restaurante' => 'Restaurante', 'bar' => 'Bar Conta', 'bar_prepago' => 'Bar Pré-pago'][$tipo],
            'seccoes' => $seccoes,
            'colunas' => $colunas,
            'nomesColunas' => self::COLUNAS_PRODUTOS,
            'soTop10' => ($opcoes['produtos'] ?? 'todos') === 'top10',
            'total' => (float) $pedidos->sum('total'),
            'total_pedidos' => $pedidos->count(),
            'vendas_por_dia' => $pedidos->groupBy(fn ($p) => $p->created_at->subHours(12)->toDateString())->map(fn ($g, $d) => ['data' => $d, 'total' => (float) $g->sum('total')])->sortKeys()->values(),
            'vendas_por_tipo' => $pedidos->groupBy('tipo')->map(fn ($g, $t) => ['tipo' => $t, 'total' => (float) $g->sum('total')])->values(),
            'vendas_bar_por_ponto' => $this->barPorPonto($pedidos),
            'caixas_por_ponto' => $this->caixasPorPonto($inicio, $fim, $pedidos),
            'produtos' => $produtos,
            'operadores' => in_array('operadores', $seccoes) ? $this->vendasPorOperador($inicio, $fim, $tipo) : [],
            'stock' => in_array('stock', $seccoes) ? $this->stockPeriodo($inicio, $fim) : [],
        ];

        return Pdf::loadView('pdf.relatorio-periodo', $dados)
            ->setPaper('a4', count($colunas) > 4 ? 'landscape' : 'portrait')
            ->download($nomeFicheiro.'.pdf');
    }

    private function numeroCsv($valor, int $casas = 2): string
    {
        return $casas === 3
            ? rtrim(rtrim(number_format((float) $valor, 3, ',', ''), '0'), ',')
            : number_format((float) $valor, $casas, ',', '');
    }

    private function tabelaCsv(array $cabecalho, array $linhas, string $nome)
    {
        return response()->streamDownload(function () use ($cabecalho, $linhas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $cabecalho, ';');
            foreach ($linhas as $linha) {
                fputcsv($out, $linha, ';');
            }
            fclose($out);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Tabela de produtos em CSV para o Excel (separador ; e virgula decimal). */
    private function produtosCsv($produtos, array $colunas, string $nome)
    {
        $numero = fn ($v, $casas = 2) => number_format((float) $v, $casas, ',', '');
        $valor = fn ($p, string $coluna) => match ($coluna) {
            'categoria' => (string) $p->categoria,
            'quantidade' => $numero($p->quantidade, 0),
            'total' => $numero($p->total),
            'custo' => $numero($p->custo_estimado),
            'margem' => $numero($p->margem_estimada),
            'margem_pct' => $numero($p->margem_percentagem, 1),
        };

        return response()->streamDownload(function () use ($produtos, $colunas, $valor) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Produto', ...array_map(fn ($c) => self::COLUNAS_PRODUTOS[$c], $colunas)], ';');
            foreach ($produtos as $p) {
                fputcsv($out, [$p->nome, ...array_map(fn ($c) => $valor($p, $c), $colunas)], ';');
            }
            fclose($out);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function topProdutos($inicio, $fim, ?int $limite, string $tipo = 'todos')
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
            ->orderBy('produtos.nome')
            ->when($limite, fn ($q) => $q->limit($limite))
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

        $manuais = FestaMovimento::where(fn ($q) => $q->whereNull('data')->orWhereBetween('data', [$inicio->toDateString(), $fim->toDateString()]))
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

    /**
     * Vendas por operador (nome de quem estava no POS): senhas/contas, total,
     * por forma de pagamento e anulacoes.
     */
    private function vendasPorOperador($inicio, $fim, string $tipo = 'todos'): array
    {
        $dia = DB::raw('DATE(DATE_SUB(created_at, INTERVAL 12 HOUR))');
        $base = fn () => Pedido::query()
            ->whereBetween($dia, [$inicio->toDateString(), $fim->toDateString()])
            ->when($tipo !== 'todos', fn ($q) => $q->where('tipo', $tipo === 'bar' ? 'bar_conta' : $tipo));
        $metodo = "COALESCE(metodo_pagamento, 'dinheiro')";
        $pago = 'total + COALESCE(caucao_cobrada, 0) - COALESCE(caucao_descontada, 0) + COALESCE(doacao, 0)';
        // Agrupa pela coluna e junta os nomes em PHP (sem espacos, vazio = "Sem operador")
        $nome = fn ($op) => trim((string) $op) !== '' ? trim((string) $op) : 'Sem operador';
        $juntar = fn ($linhas, array $campos) => $linhas->groupBy(fn ($l) => $nome($l->operador_nome))
            ->map(fn ($g) => (object) collect($campos)->mapWithKeys(fn ($c) => [$c => $g->sum($c)])->all());

        $vendas = $juntar($base()
            ->where(fn ($q) => $q->where('estado', 'entregue')->orWhere('pago_antecipado', true))
            ->groupBy('operador_nome')
            ->selectRaw("operador_nome, COUNT(*) as pedidos, SUM(total) as total,
                SUM(CASE WHEN $metodo = 'dinheiro' THEN $pago ELSE 0 END) as dinheiro,
                SUM(CASE WHEN $metodo = 'mbway' THEN $pago ELSE 0 END) as mbway,
                SUM(CASE WHEN $metodo NOT IN ('dinheiro', 'mbway') THEN $pago ELSE 0 END) as outros")
            ->get(), ['pedidos', 'total', 'dinheiro', 'mbway', 'outros']);

        $anuladas = $juntar($base()->where('estado', 'cancelado')
            ->groupBy('operador_nome')
            ->selectRaw('operador_nome, COUNT(*) as n, SUM(COALESCE(valor_devolvido, 0)) as devolvido')
            ->get(), ['n', 'devolvido']);

        return $vendas->keys()->merge($anuladas->keys())->unique()
            ->map(fn ($op) => [
                'operador' => $op,
                'pedidos' => (int) ($vendas[$op]->pedidos ?? 0),
                'total' => round((float) ($vendas[$op]->total ?? 0), 2),
                'dinheiro' => round((float) ($vendas[$op]->dinheiro ?? 0), 2),
                'mbway' => round((float) ($vendas[$op]->mbway ?? 0), 2),
                'outros' => round((float) ($vendas[$op]->outros ?? 0), 2),
                'anuladas' => (int) ($anuladas[$op]->n ?? 0),
                'devolvido' => round((float) ($anuladas[$op]->devolvido ?? 0), 2),
            ])
            ->sortByDesc('total')->values()->all();
    }

    /**
     * Stock dos produtos com "gerir stock" no periodo: com que stock comecou,
     * o que entrou (faturas de compra), o que se vendeu e com quanto acabou.
     * Parte do stock atual e anda para tras; acertos a mao no stock baralham.
     */
    private function stockPeriodo($inicio, $fim): array
    {
        $dia = 'DATE(DATE_SUB(pedidos.created_at, INTERVAL 12 HOUR))';
        $vendido = fn ($ate) => DB::table('pedido_items')
            ->join('pedidos', 'pedido_items.pedido_id', '=', 'pedidos.id')
            ->where(fn ($q) => $q->where('pedidos.estado', 'entregue')->orWhere('pedidos.pago_antecipado', true))
            ->whereRaw("$dia >= ?", [$inicio->toDateString()])
            ->when($ate, fn ($q) => $q->whereRaw("$dia <= ?", [$fim->toDateString()]))
            ->groupBy('pedido_items.produto_id')
            ->selectRaw('pedido_items.produto_id as id, SUM(pedido_items.quantidade) as qtd')
            ->pluck('qtd', 'id');
        $comprado = fn ($ate) => DB::table('fatura_compra_items')
            ->join('fatura_compras', 'fatura_compra_items.fatura_compra_id', '=', 'fatura_compras.id')
            ->where('fatura_compras.data', '>=', $inicio->toDateString())
            ->when($ate, fn ($q) => $q->where('fatura_compras.data', '<=', $fim->toDateString()))
            ->groupBy('fatura_compra_items.produto_id')
            ->selectRaw('fatura_compra_items.produto_id as id, SUM(fatura_compra_items.quantidade - COALESCE(fatura_compra_items.quantidade_devolvida, 0)) as qtd')
            ->pluck('qtd', 'id');

        $vendidoPeriodo = $vendido(true);
        $vendidoDesde = $vendido(false);
        $compradoPeriodo = $comprado(true);
        $compradoDesde = $comprado(false);

        return Produto::with('categoria')->where('gerir_stock', true)->orderBy('nome')->get()
            ->map(function (Produto $p) use ($vendidoPeriodo, $vendidoDesde, $compradoPeriodo, $compradoDesde) {
                $atual = (float) $p->stock_atual;
                $inicial = $atual + (float) ($vendidoDesde[$p->id] ?? 0) - (float) ($compradoDesde[$p->id] ?? 0);
                $entradas = (float) ($compradoPeriodo[$p->id] ?? 0);
                $vendidos = (float) ($vendidoPeriodo[$p->id] ?? 0);

                return [
                    'nome' => $p->nome,
                    'categoria' => $p->categoria?->nome,
                    'inicial' => round($inicial, 3),
                    'entradas' => round($entradas, 3),
                    'vendido' => round($vendidos, 3),
                    'final' => round($inicial + $entradas - $vendidos, 3),
                    'atual' => round($atual, 3),
                ];
            })->all();
    }

    private function festaCustos($inicio, $fim): array
    {
        $stock = [
            ['label' => 'Compras de stock', 'valor' => (float) FaturaCompra::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])->sum('total')],
        ];

        $manuais = FestaMovimento::where(fn ($q) => $q->whereNull('data')->orWhereBetween('data', [$inicio->toDateString(), $fim->toDateString()]))
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
