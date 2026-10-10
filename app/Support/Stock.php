<?php

namespace App\Support;

use App\Models\Produto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Movimentos de stock das vendas. So mexe em produtos com "gerir_stock"
 * ligado; os outros ficam como estao (o stock deles so muda pelas faturas).
 */
class Stock
{
    /** Quando ligado, as vendas descontam sem recusar por falta de stock. */
    private static bool $semLimite = false;

    /**
     * Vendas feitas sem internet: o dinheiro ja mudou de maos, por isso
     * descontam sempre (no minimo fica a zero) e nunca sao recusadas.
     */
    public static function semLimite(callable $fazer): mixed
    {
        $antes = self::$semLimite;
        self::$semLimite = true;

        try {
            return $fazer();
        } finally {
            self::$semLimite = $antes;
        }
    }

    /**
     * Tira $quantidade do stock. Se nao houver que chegue, recusa a venda
     * (a menos que $forcar, usado ao "des-anular" um pedido).
     */
    public static function retirar(?int $produtoId, float $quantidade, bool $forcar = false): void
    {
        if (! $produtoId || $quantidade <= 0) {
            return;
        }

        $query = DB::table('produtos')->where('id', $produtoId)->where('gerir_stock', true);

        if ($forcar || self::$semLimite) {
            $query->decrement('stock_atual', $quantidade);
            DB::table('produtos')->where('id', $produtoId)->where('stock_atual', '<', 0)->update(['stock_atual' => 0]);

            return;
        }

        // Verificar e descontar na mesma instrucao: dois terminais a vender
        // a ultima unidade ao mesmo tempo nao a vendem os dois.
        if ((clone $query)->where('stock_atual', '>=', $quantidade)->decrement('stock_atual', $quantidade)) {
            return;
        }

        $produto = Produto::query()->find($produtoId, ['id', 'nome', 'stock_atual', 'gerir_stock']);

        if (! $produto || ! $produto->gerir_stock) {
            return;
        }

        $resta = (float) $produto->stock_atual;

        throw ValidationException::withMessages([
            'stock' => $resta <= 0
                ? "{$produto->nome} esgotou."
                : 'So '.($resta == 1 ? 'resta' : 'restam').' '.self::formatar($resta)." de {$produto->nome}.",
        ]);
    }

    public static function repor(?int $produtoId, float $quantidade): void
    {
        if (! $produtoId || $quantidade <= 0) {
            return;
        }

        DB::table('produtos')->where('id', $produtoId)->where('gerir_stock', true)->increment('stock_atual', $quantidade);
    }

    private static function formatar(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 3, ',', ''), '0'), ',');
    }
}
