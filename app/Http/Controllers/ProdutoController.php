<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Produto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProdutoController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:produtos.ver')->only('index');
        $this->middleware('permission:produtos.gerir')->only(['store', 'update', 'destroy']);
    }

    public function index(): Response
    {
        $categoriasRestaurante = ['bebidas', 'frango', 'acompanhamentos', 'comida', 'sobremesas'];
        $ordemSecoes = "FIELD(secao, 'bebidas', 'frango', 'acompanhamentos', 'comida', 'sobremesas')";

        return Inertia::render('Produtos/Index', [
            'categorias' => Categoria::with(['produtos' => fn ($query) => $query->orderBy('nome')])
                ->whereIn('secao', $categoriasRestaurante)
                ->orderByRaw($ordemSecoes)
                ->orderBy('nome')
                ->get(),
            'categoriasOptions' => Categoria::whereIn('secao', $categoriasRestaurante)
                ->orderByRaw($ordemSecoes)
                ->orderBy('nome')
                ->get(['id', 'nome', 'secao']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $componentes = $data['componentes'] ?? [];
        unset($data['componentes']);

        $produto = Produto::create($data);
        $this->syncComponentes($produto, $componentes);

        return back()->with('success', 'Produto criado.');
    }

    public function update(Request $request, Produto $produto): RedirectResponse
    {
        $data = $this->validated($request);
        $syncComponentes = $request->has('componentes');
        $componentes = $data['componentes'] ?? [];
        unset($data['componentes']);

        $produto->update($data);
        if ($syncComponentes) {
            $this->syncComponentes($produto, $componentes);
        }

        return back()->with('success', 'Produto atualizado.');
    }

    public function destroy(Produto $produto): RedirectResponse
    {
        $produto->delete();

        return back()->with('success', 'Produto eliminado.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'categoria_id' => ['required', 'exists:categorias,id'],
            'nome' => ['required', 'string', 'max:255'],
            'preco' => ['required', 'numeric', 'min:0'],
            'custo_compra_unitario' => ['nullable', 'numeric', 'min:0'],
            'unidade_compra' => ['nullable', 'string', 'max:20'],
            'custo_preparacao_unitario' => ['nullable', 'numeric', 'min:0'],
            'stock_atual' => ['nullable', 'numeric', 'min:0'],
            'disponivel' => ['boolean'],
            'disponivel_restaurante' => ['boolean'],
            'disponivel_bar' => ['boolean'],
            'componentes' => ['nullable', 'array'],
            'componentes.*.componente_id' => ['required_with:componentes', 'exists:produtos,id'],
            'componentes.*.quantidade' => ['required_with:componentes', 'numeric', 'min:0.0001'],
        ]);

        if (! $request->isMethod('put') && ! $request->isMethod('patch')) {
            $data['custo_compra_unitario'] = $data['custo_compra_unitario'] ?? 0;
            $data['unidade_compra'] = $data['unidade_compra'] ?? 'un';
            $data['unidade_compra'] = $data['unidade_compra'] ?: 'un';
            $data['custo_preparacao_unitario'] = $data['custo_preparacao_unitario'] ?? 0;
        } else {
            unset($data['custo_compra_unitario'], $data['unidade_compra'], $data['custo_preparacao_unitario']);

            foreach (['custo_compra_unitario', 'unidade_compra', 'custo_preparacao_unitario'] as $campo) {
                if ($request->has($campo)) {
                    $data[$campo] = $request->input($campo) ?: ($campo === 'unidade_compra' ? 'un' : 0);
                }
            }
        }

        $data['stock_atual'] = $data['stock_atual'] ?? 0;

        return $data;
    }

    private function syncComponentes(Produto $produto, array $componentes): void
    {
        $linhas = collect($componentes)
            ->filter(fn ($componente) => (int) ($componente['componente_id'] ?? 0) !== (int) $produto->id)
            ->filter(fn ($componente) => (float) ($componente['quantidade'] ?? 0) > 0)
            ->unique('componente_id')
            ->map(fn ($componente) => [
                'componente_id' => $componente['componente_id'],
                'quantidade' => $componente['quantidade'],
            ])
            ->values();

        $produto->componentes()->delete();
        $produto->componentes()->createMany($linhas->all());
    }
}
