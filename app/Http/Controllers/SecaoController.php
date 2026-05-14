<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Mesa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecaoController extends Controller
{
    public function bebidas(): Response
    {
        return $this->ecra('bebidas', 'BEBIDAS');
    }

    public function cozinha(): Response
    {
        return $this->comida();
    }

    public function comida(): Response
    {
        return $this->ecra('comida', 'COMIDA');
    }

    public function sobremesas(): Response
    {
        return $this->ecra('sobremesas', 'SOBREMESAS');
    }

    public function acompanhamentos(): Response
    {
        return $this->comida();
    }

    public function servico(): Response
    {
        return $this->comida();
    }

    public function bar(): Response
    {
        $items = Pedido::with('items.produto')
            ->barPrepago()
            ->where('estado', 'pronto')
            ->whereDate('created_at', today())
            ->orderBy('numero_senha')
            ->get()
            ->map(fn ($pedido) => [
                'pedido_id' => $pedido->id,
                'mesa' => 'Senha #'.$pedido->numero_senha,
                'urgente' => false,
                'items' => $pedido->items->values(),
            ]);

        return Inertia::render('Secao/Ecra', [
            'titulo' => 'BAR',
            'itemsPorMesa' => $items->values(),
            'tem_urgentes' => false,
            'modoBar' => true,
        ]);
    }

    private function ecra(string $secao, string $titulo): Response
    {
        $items = PedidoItem::with('pedido.mesa', 'produto')
            ->whereHas('pedido', fn ($query) => $query->where('tipo', 'restaurante'))
            ->where('secao', $secao)
            ->where('estado', 'pendente')
            ->orderByDesc('prioridade')
            ->oldest()
            ->get()
            ->groupBy(fn ($item) => $item->pedido->mesa?->designacao ?? 'Para levar #'.$item->pedido_id)
            ->map(fn ($grupo, $mesa) => [
                'mesa' => $mesa,
                'urgente' => $grupo->contains('prioridade', true),
                'items' => $grupo->sortByDesc('prioridade')->values(),
            ])
            ->sortByDesc('urgente');

        return Inertia::render('Secao/Ecra', [
            'titulo' => $titulo,
            'itemsPorMesa' => $items->values(),
            'tem_urgentes' => PedidoItem::urgentes()
                ->whereHas('pedido', fn ($query) => $query->where('tipo', 'restaurante'))
                ->where('secao', $secao)
                ->exists(),
        ]);
    }

    public function pronto(Request $request, PedidoItem $pedidoItem): RedirectResponse
    {
        $data = $request->validate([
            'quantidade' => ['nullable', 'integer', 'min:1'],
        ]);

        $pedidoItem->load('pedido.mesa', 'produto');
        $quantidadePronta = min((int) ($data['quantidade'] ?? $pedidoItem->quantidade), (int) $pedidoItem->quantidade);

        if ($quantidadePronta < $pedidoItem->quantidade) {
            $itemPronto = $pedidoItem->replicate();
            $itemPronto->quantidade = $quantidadePronta;
            $itemPronto->estado = 'pronto';
            $itemPronto->save();

            $pedidoItem->decrement('quantidade', $quantidadePronta);
        } else {
            $pedidoItem->update(['estado' => 'pronto']);
        }

        if (strcasecmp($pedidoItem->produto?->nome, 'Limpar mesa') === 0) {
            $pedidoItem->pedido->update(['estado' => 'entregue']);
            $this->libertarMesaDoPedido($pedidoItem->pedido);
        }

        return back();
    }

    public function retirar(Pedido $pedido): RedirectResponse
    {
        $pedido->update(['estado' => 'entregue']);
        $this->libertarMesaDoPedido($pedido);

        return back();
    }

    private function libertarMesaDoPedido(Pedido $pedido): void
    {
        $mesa = $pedido->fresh('mesa.mesaPrincipal')?->mesa;

        if (! $mesa) {
            return;
        }

        $mesaPrincipal = $mesa->mesaPrincipal ?: $mesa;
        $mesaPrincipal->load('submesas');

        foreach ($mesaPrincipal->submesas as $submesa) {
            $submesa->update([
                'estado' => $this->temPedidosAtivosNaMesa($submesa) ? 'ocupada' : 'livre',
            ]);
        }

        if (! $this->temPedidosAtivosNaMesaCompleta($mesaPrincipal)) {
            $this->normalizarMesa($mesaPrincipal);

            return;
        }

        $mesaPrincipal->update(['estado' => 'ocupada']);
    }

    private function temPedidosAtivosNaMesa(Mesa $mesa): bool
    {
        return $mesa->pedidos()
            ->whereIn('estado', ['pendente', 'preparacao', 'pronto'])
            ->exists();
    }

    private function temPedidosAtivosNaMesaCompleta(Mesa $mesa): bool
    {
        return $this->temPedidosAtivosNaMesa($mesa)
            || $mesa->submesas()
                ->whereHas('pedidos', fn ($query) => $query->whereIn('estado', ['pendente', 'preparacao', 'pronto']))
                ->exists();
    }

    private function normalizarMesa(Mesa $mesa): void
    {
        $mesa->load('submesas');
        $submesaIds = $mesa->submesas->pluck('id');

        if ($submesaIds->isNotEmpty()) {
            Pedido::whereIn('mesa_id', $submesaIds)->update(['mesa_id' => $mesa->id]);
            $mesa->submesas()->delete();
        }

        $mesa->update(['estado' => 'livre']);
    }
}
