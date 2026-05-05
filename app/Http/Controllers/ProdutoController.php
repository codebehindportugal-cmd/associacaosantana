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
        $this->middleware('permission:pedidos.ver')->only('index');
        $this->middleware('permission:pedidos.editar')->only(['store', 'update', 'destroy']);
    }

    public function index(): Response
    {
        $categoriasRestaurante = ['bebidas', 'comida', 'sobremesas'];

        return Inertia::render('Produtos/Index', [
            'categorias' => Categoria::with(['produtos' => fn ($query) => $query->orderBy('nome')])
                ->whereIn('secao', $categoriasRestaurante)
                ->orderByRaw("FIELD(secao, 'bebidas', 'comida', 'sobremesas')")
                ->orderBy('nome')
                ->get(),
            'categoriasOptions' => Categoria::whereIn('secao', $categoriasRestaurante)
                ->orderByRaw("FIELD(secao, 'bebidas', 'comida', 'sobremesas')")
                ->orderBy('nome')
                ->get(['id', 'nome', 'secao']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Produto::create($this->validated($request));

        return back()->with('success', 'Produto criado.');
    }

    public function update(Request $request, Produto $produto): RedirectResponse
    {
        $produto->update($this->validated($request));

        return back()->with('success', 'Produto atualizado.');
    }

    public function destroy(Produto $produto): RedirectResponse
    {
        $produto->delete();

        return back()->with('success', 'Produto eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'categoria_id' => ['required', 'exists:categorias,id'],
            'nome' => ['required', 'string', 'max:255'],
            'preco' => ['required', 'numeric', 'min:0'],
            'disponivel' => ['boolean'],
        ]);
    }
}
