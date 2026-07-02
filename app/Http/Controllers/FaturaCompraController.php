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
        $this->middleware('permission:produtos.gerir')->only('store');
    }

    public function index(): Response
    {
        return Inertia::render('FaturasCompras/Index', [
            'produtosOptions' => Produto::query()
                ->orderBy('nome')
                ->get(['id', 'nome', 'stock_atual']),
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
                Produto::whereKey($item['produto_id'])->increment('stock_atual', $item['quantidade']);
            }
        });

        return back()->with('success', 'Fatura registada e stock atualizado.');
    }
}
