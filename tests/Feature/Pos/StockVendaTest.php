<?php

namespace Tests\Feature\Pos;

use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockVendaTest extends TestCase
{
    use RefreshDatabase;

    private function produto(array $attrs = []): Produto
    {
        $categoria = Categoria::create(['nome' => 'Bebidas', 'secao' => 'bebidas']);

        return Produto::create(array_merge([
            'categoria_id' => $categoria->id, 'nome' => 'Sumol', 'preco' => 1.5,
            'stock_atual' => 3, 'gerir_stock' => true,
            'disponivel' => true, 'disponivel_bar' => true, 'disponivel_restaurante' => true,
        ], $attrs));
    }

    private function pedido(): Pedido
    {
        return Pedido::create(['tipo' => 'bar_prepago', 'estado' => 'pronto', 'total' => 0]);
    }

    public function test_venda_desconta_e_esgotado_sai_da_lista(): void
    {
        $produto = $this->produto();
        $pedido = $this->pedido();

        $pedido->items()->create(['produto_id' => $produto->id, 'quantidade' => 2, 'preco_unitario' => 1.5]);
        $this->assertEquals(1, (float) $produto->fresh()->stock_atual);
        $this->assertTrue(Produto::disponiveisBar()->whereKey($produto->id)->exists());

        $pedido->items()->create(['produto_id' => $produto->id, 'quantidade' => 1, 'preco_unitario' => 1.5]);
        $this->assertEquals(0, (float) $produto->fresh()->stock_atual);
        $this->assertFalse(Produto::disponiveisBar()->whereKey($produto->id)->exists());
        $this->assertFalse(Produto::disponiveisRestaurante()->whereKey($produto->id)->exists());
    }

    public function test_nao_vende_mais_do_que_ha(): void
    {
        $produto = $this->produto(['stock_atual' => 1]);

        $this->expectException(ValidationException::class);
        $this->pedido()->items()->create(['produto_id' => $produto->id, 'quantidade' => 2, 'preco_unitario' => 1.5]);
    }

    public function test_sem_gerir_stock_nao_mexe(): void
    {
        $produto = $this->produto(['gerir_stock' => false, 'stock_atual' => 0]);
        $this->pedido()->items()->create(['produto_id' => $produto->id, 'quantidade' => 5, 'preco_unitario' => 1.5]);

        $this->assertEquals(0, (float) $produto->fresh()->stock_atual);
        $this->assertTrue(Produto::disponiveisBar()->whereKey($produto->id)->exists());
    }

    public function test_anular_unidade_linha_e_pedido_repoe(): void
    {
        $produto = $this->produto(['stock_atual' => 10]);
        $pedido = $this->pedido();
        $item = $pedido->items()->create(['produto_id' => $produto->id, 'quantidade' => 4, 'preco_unitario' => 1.5]);
        $this->assertEquals(6, (float) $produto->fresh()->stock_atual);

        $item->decrement('quantidade', 1);
        $this->assertEquals(7, (float) $produto->fresh()->stock_atual);

        $item->increment('quantidade', 2);
        $this->assertEquals(5, (float) $produto->fresh()->stock_atual);

        $pedido->update(['estado' => 'cancelado']);
        $this->assertEquals(10, (float) $produto->fresh()->stock_atual);

        // apagar linha de pedido anulado nao repoe outra vez
        $item->fresh()->delete();
        $this->assertEquals(10, (float) $produto->fresh()->stock_atual);
    }

    public function test_dividir_linha_pronta_com_stock_a_zero(): void
    {
        $produto = $this->produto(['stock_atual' => 3]);
        $item = $this->pedido()->items()->create(['produto_id' => $produto->id, 'quantidade' => 3, 'preco_unitario' => 1.5]);

        $pronto = $item->replicate();
        $item->decrement('quantidade', 1);
        $pronto->quantidade = 1;
        $pronto->estado = 'pronto';
        $pronto->save();

        $this->assertEquals(0, (float) $produto->fresh()->stock_atual);
        $this->assertEquals(3, PedidoItem::sum('quantidade'));
    }
}
