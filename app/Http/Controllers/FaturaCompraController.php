<?php

namespace App\Http\Controllers;

use App\Models\FaturaCompra;
use App\Models\Produto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FaturaCompraController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:produtos.ver')->only('index');
        $this->middleware('permission:produtos.gerir')->only(['store', 'pagar', 'devolver']);
    }

    public function index(): Response
    {
        $produtos = Produto::query()
            ->with('componentes.componente:id,nome,custo_compra_unitario,unidade_compra')
            ->orderBy('nome')
            ->get([
                'id',
                'categoria_id',
                'nome',
                'preco',
                'custo_compra_unitario',
                'unidade_compra',
                'custo_preparacao_unitario',
                'stock_atual',
                'disponivel',
                'disponivel_restaurante',
                'disponivel_bar',
            ]);

        $produtos->each(function (Produto $produto) {
            $produto->setAttribute('custo_unitario_estimado', $produto->custoUnitarioEstimado());
            $produto->setAttribute('margem_unitaria_estimada', $produto->margemUnitarioEstimado());
            $produto->setAttribute('margem_percentagem_estimada', (float) $produto->preco > 0 ? ($produto->margemUnitarioEstimado() / (float) $produto->preco) * 100 : 0);
        });

        return Inertia::render('FaturasCompras/Index', [
            'produtosOptions' => $produtos,
            'faturasRecentes' => FaturaCompra::query()
                ->with(['items.produto:id,nome'])
                ->latest('data')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fornecedor' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:255'],
            'data' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produto_id' => ['required', 'exists:produtos,id'],
            'items.*.quantidade' => ['required', 'numeric', 'min:0.001'],
            'items.*.preco_unitario' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            $items = collect($validated['items'])
                ->map(function (array $item) {
                    $quantidade = (float) $item['quantidade'];
                    $precoUnitario = (float) ($item['preco_unitario'] ?? 0);

                    return [
                        'produto_id' => $item['produto_id'],
                        'quantidade' => $quantidade,
                        'preco_unitario' => $precoUnitario,
                        'total' => round($quantidade * $precoUnitario, 2),
                    ];
                });

            $fatura = FaturaCompra::create([
                'fornecedor' => $validated['fornecedor'] ?? null,
                'numero' => $validated['numero'] ?? null,
                'data' => $validated['data'],
                'total' => $items->sum('total'),
            ]);

            foreach ($items as $item) {
                $fatura->items()->create($item);
                $produto = Produto::find($item['produto_id']);
                if (! $produto) {
                    continue;
                }

                $stockAtual = (float) $produto->stock_atual;
                $quantidade = (float) $item['quantidade'];
                $precoUnitario = (float) $item['preco_unitario'];
                $novoStock = $stockAtual + $quantidade;
                $custoAtual = (float) $produto->custo_compra_unitario;
                $novoCusto = $novoStock > 0
                    ? (($stockAtual * $custoAtual) + ($quantidade * $precoUnitario)) / $novoStock
                    : $precoUnitario;

                $produto->update([
                    'stock_atual' => $novoStock,
                    'custo_compra_unitario' => round($novoCusto, 4),
                ]);
            }
        });

        return back()->with('success', 'Fatura registada e stock atualizado.');
    }

    public function pagar(FaturaCompra $fatura): RedirectResponse
    {
        $fatura->update(['pago' => ! $fatura->pago]);

        return back()->with('success', $fatura->pago ? 'Fatura marcada como paga.' : 'Fatura marcada como não paga.');
    }

    public function devolver(Request $request, FaturaCompra $fatura): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantidade_devolvida' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($fatura, $validated) {
            foreach ($validated['items'] as $itemData) {
                $item = $fatura->items()->findOrFail($itemData['id']);
                $novaDevolvida = (float) $itemData['quantidade_devolvida'];

                // Não pode devolver mais do que comprou
                if ($novaDevolvida > (float) $item->quantidade) {
                    $novaDevolvida = (float) $item->quantidade;
                }

                $delta = $novaDevolvida - (float) $item->quantidade_devolvida;

                if (abs($delta) < 0.0001) {
                    continue;
                }

                $item->update(['quantidade_devolvida' => $novaDevolvida]);

                $produto = Produto::find($item->produto_id);
                if ($produto) {
                    $novoStock = max(0, (float) $produto->stock_atual - $delta);
                    $produto->update(['stock_atual' => $novoStock]);
                }
            }
        });

        return back()->with('success', 'Devoluções registadas e stock atualizado.');
    }
}
