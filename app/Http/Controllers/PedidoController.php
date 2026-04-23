<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PedidoController extends Controller
{
    public function index(): View
    {
        $pedidos = Pedido::with('mesa')->orderBy('created_at', 'desc')->paginate(20);

        return view('pedidos.index', compact('pedidos'));
    }

    public function create(): View
    {
        $mesas = Mesa::orderBy('numero')->get();

        return view('pedidos.create', compact('mesas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'mesa_id' => 'required|exists:mesas,id',
            'estado' => 'required|in:pendente,preparacao,pronto,entregue',
            'total' => 'required|numeric|min:0',
            'observacoes' => 'nullable|string',
        ]);

        Pedido::create($request->only(['mesa_id', 'estado', 'total', 'observacoes']));

        return redirect()->route('pedidos.index')->with('success', 'Pedido criado com sucesso.');
    }

    public function show(Pedido $pedido): View
    {
        $pedido->load('mesa', 'items.produto');

        return view('pedidos.show', compact('pedido'));
    }

    public function update(Request $request, Pedido $pedido): RedirectResponse
    {
        $request->validate([
            'estado' => 'required|in:pendente,preparacao,pronto,entregue',
            'total' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
        ]);

        $pedido->update($request->only(['estado', 'total', 'observacoes']));

        return redirect()->route('pedidos.show', $pedido)->with('success', 'Pedido atualizado com sucesso.');
    }

    public function destroy(Pedido $pedido): RedirectResponse
    {
        $pedido->delete();

        return redirect()->route('pedidos.index')->with('success', 'Pedido excluído com sucesso.');
    }
}
