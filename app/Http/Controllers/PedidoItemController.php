<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Mesa;
use App\Models\Produto;
use App\Services\PrintJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PedidoItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:pedidos.criar')->only('store');
        $this->middleware('permission:pedidos.editar')->only(['update', 'destroy']);
    }

    public function store(Request $request, PrintJobService $printJobs): RedirectResponse
    {
        $data = $request->validate([
            'pedido_id' => ['required', 'exists:pedidos,id'],
            'produto_id' => ['required', Rule::exists('produtos', 'id')->where('disponivel', true)],
            'quantidade' => ['required', 'integer', 'min:1'],
            'prioridade' => ['nullable', 'boolean'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);
        $produto = Produto::with('categoria')->findOrFail($data['produto_id']);
        $pedido = Pedido::findOrFail($data['pedido_id']);
        $itemExistente = $pedido->items()
            ->where('produto_id', $produto->id)
            ->where('estado', 'pendente')
            ->where('prioridade', (bool) ($data['prioridade'] ?? false))
            ->where('observacoes', $data['observacoes'] ?? null)
            ->first();

        if ($itemExistente) {
            $itemExistente->increment('quantidade', (int) $data['quantidade']);
        } else {
            $pedido->items()->create([
                'produto_id' => $produto->id,
                'quantidade' => $data['quantidade'],
                'preco_unitario' => $produto->preco,
                'secao' => $produto->categoria->secao,
                'prioridade' => $data['prioridade'] ?? false,
                'observacoes' => $data['observacoes'] ?? null,
            ]);
        }
        $pedido->update(['total' => $pedido->fresh('items')->total_calculado]);
        $printJobs->criarItemPedido($pedido->fresh('mesa.mesaPrincipal', 'user', 'pos'), [
            'quantidade' => (int) $data['quantidade'],
            'nome' => $produto->nome,
            'observacoes' => $data['observacoes'] ?? null,
        ], $produto->categoria->secao);

        return back()->with('success', 'Item adicionado.');
    }

    public function update(Request $request, PedidoItem $pedidoItem): RedirectResponse
    {
        $pedidoItem->update($request->validate([
            'estado' => ['sometimes', 'required', 'in:pendente,pronto'],
            'prioridade' => ['sometimes', 'boolean'],
            'observacoes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]));
        $this->libertarMesaSeForLimpeza($pedidoItem->fresh(['pedido.mesa.mesaPrincipal', 'produto']));

        return back()->with('success', 'Item atualizado.');
    }

    public function destroy(PedidoItem $pedidoItem, PrintJobService $printJobs): RedirectResponse
    {
        $pedido = $pedidoItem->pedido;
        $pedidoItem->loadMissing('produto.categoria');
        $quantidadeAnulada = 1;
        $secao = $pedidoItem->secao ?: ($pedidoItem->produto?->categoria?->secao ?? null);

        if ($pedidoItem->quantidade > $quantidadeAnulada) {
            $pedidoItem->decrement('quantidade', $quantidadeAnulada);
        } else {
            $pedidoItem->delete();
        }

        $pedido->update(['total' => $pedido->fresh('items')->total_calculado]);
        $printJobs->criarAnulacaoItemPedido($pedido->fresh('mesa.mesaPrincipal', 'user', 'pos'), [
            'quantidade' => $quantidadeAnulada,
            'nome' => $pedidoItem->produto?->nome ?? 'Produto',
            'observacoes' => $pedidoItem->observacoes,
        ], $secao);

        return back()->with('success', 'Item anulado.');
    }

    private function libertarMesaSeForLimpeza(PedidoItem $pedidoItem): void
    {
        if ($pedidoItem->estado !== 'pronto' || strcasecmp($pedidoItem->produto?->nome, 'Limpar mesa') !== 0) {
            return;
        }

        $pedidoItem->pedido->update(['estado' => 'entregue']);
        $this->libertarMesaDoPedido($pedidoItem->pedido);
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
