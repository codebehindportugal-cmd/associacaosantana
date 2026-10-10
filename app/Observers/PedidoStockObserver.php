<?php

namespace App\Observers;

use App\Models\Pedido;
use App\Support\Stock;

/**
 * Anular um pedido (senha do bar, pedido do restaurante) devolve ao stock
 * tudo o que tinha. Apagar pedidos (limpezas de dados) nao mexe no stock.
 */
class PedidoStockObserver
{
    public function updated(Pedido $pedido): void
    {
        if (! $pedido->wasChanged('estado')) {
            return;
        }

        $antes = $pedido->getOriginal('estado');
        $agora = $pedido->estado;

        if ($agora === 'cancelado' && $antes !== 'cancelado') {
            $pedido->items()->get()->each(fn ($item) => Stock::repor($item->produto_id, (float) $item->quantidade));
        } elseif ($antes === 'cancelado' && $agora !== 'cancelado') {
            $pedido->items()->get()->each(fn ($item) => Stock::retirar($item->produto_id, (float) $item->quantidade, forcar: true));
        }
    }
}
