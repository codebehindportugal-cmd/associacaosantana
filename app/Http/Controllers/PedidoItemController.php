<?php

namespace App\Http\Controllers;

use App\Models\PedidoItem;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class PedidoItemController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'pedido_id' => 'required|exists:pedidos,id',
            'produto_id' => 'required|exists:produtos,id',
            'quantidade' => 'required|integer|min:1',
            'preco_unitario' => 'required|numeric|min:0',
            'estado' => 'required|in:pendente,pronto',
            'secao' => 'required|in:bebidas,acompanhamentos,sobremesas',
        ]);

        PedidoItem::create($request->only(['pedido_id', 'produto_id', 'quantidade', 'preco_unitario', 'estado', 'secao']));

        return back()->with('success', 'Item de pedido adicionado com sucesso.');
    }

    public function update(Request $request, PedidoItem $pedidoItem): RedirectResponse
    {
        $request->validate([
            'estado' => 'required|in:pendente,pronto',
        ]);

        $pedidoItem->update($request->only(['estado']));

        return back()->with('success', 'Item de pedido atualizado para pronto.');
    }

    public function destroy(PedidoItem $pedidoItem): RedirectResponse
    {
        $pedidoItem->delete();

        return back()->with('success', 'Item de pedido removido com sucesso.');
    }
}
