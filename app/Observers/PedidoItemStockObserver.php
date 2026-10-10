<?php

namespace App\Observers;

use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Support\Stock;

/**
 * Cada linha vendida desconta do stock do produto (se o produto gere stock).
 * Anular uma unidade ou apagar a linha repoe; aumentar a quantidade desconta.
 * Linhas de pedidos anulados nao contam (o PedidoStockObserver ja repos).
 */
class PedidoItemStockObserver
{
    public function creating(PedidoItem $item): void
    {
        if ($this->pedidoAnulado($item)) {
            return;
        }

        Stock::retirar($item->produto_id, (float) $item->quantidade);
    }

    public function updating(PedidoItem $item): void
    {
        if (! $item->isDirty(['quantidade', 'produto_id']) || $this->pedidoAnulado($item)) {
            return;
        }

        $produtoAntes = (int) $item->getOriginal('produto_id');
        $quantidadeAntes = (float) $item->getOriginal('quantidade');

        if ($produtoAntes !== (int) $item->produto_id) {
            Stock::retirar($item->produto_id, (float) $item->quantidade);
            Stock::repor($produtoAntes, $quantidadeAntes);

            return;
        }

        $delta = (float) $item->quantidade - $quantidadeAntes;

        if ($delta > 0) {
            Stock::retirar($item->produto_id, $delta);
        } elseif ($delta < 0) {
            Stock::repor($item->produto_id, -$delta);
        }
    }

    public function deleted(PedidoItem $item): void
    {
        if ($this->pedidoAnulado($item)) {
            return;
        }

        Stock::repor($item->produto_id, (float) $item->quantidade);
    }

    private function pedidoAnulado(PedidoItem $item): bool
    {
        if (! $item->pedido_id) {
            return false;
        }

        $estado = $item->relationLoaded('pedido')
            ? $item->pedido?->estado
            : Pedido::whereKey($item->pedido_id)->value('estado');

        return $estado === 'cancelado';
    }
}
