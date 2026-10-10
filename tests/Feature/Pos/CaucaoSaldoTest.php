<?php

namespace Tests\Feature\Pos;

use App\Models\CaixaDiaria;
use App\Models\CaucaoDevolucao;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\PosSession;
use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaucaoSaldoTest extends TestCase
{
    use RefreshDatabase;

    private PosSession $pos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pos = PosSession::create(['nome' => 'Bar 1', 'pin' => '1234', 'localizacao' => 'Bar', 'tipo' => 'bar', 'ativo' => true]);
        CaixaDiaria::create(['data' => now()->toDateString(), 'ponto' => 'Bar', 'fundo_maneio' => 50, 'estado' => 'aberta']);
    }

    private function sessao(): array
    {
        return ['pos_id' => $this->pos->id, 'pos_nome' => 'Bar 1', 'pos_tipo' => 'bar', 'pos_localizacao' => 'Bar', 'pos_operador' => 'Ana'];
    }

    public function test_saldo_maior_que_a_senha_devolve_o_resto(): void
    {
        $categoria = Categoria::create(['nome' => 'Bebidas', 'secao' => 'bebidas']);
        $metro = Produto::create(['categoria_id' => $categoria->id, 'nome' => 'Metro', 'preco' => 12, 'caucao' => 3, 'disponivel' => true, 'disponivel_bar' => true]);
        $imperial = Produto::create(['categoria_id' => $categoria->id, 'nome' => 'Imperial', 'preco' => 2.5, 'disponivel' => true, 'disponivel_bar' => true]);

        $this->withSession($this->sessao())->post(route('pos.prepago.store'), [
            'items' => [['produto_id' => $imperial->id, 'quantidade' => 1]],
            'devolvidos' => [['produto_id' => $metro->id, 'quantidade' => 1]],
            'valor_recebido' => 0,
            'troco' => 0.5,
            'metodo_pagamento' => 'mbway',
        ])->assertSessionHasNoErrors();

        $pedido = Pedido::firstOrFail();
        $this->assertSame('dinheiro', $pedido->metodo_pagamento);
        $this->assertEquals(3, (float) $pedido->caucao_descontada);
        $this->assertEquals(0.5, (float) $pedido->troco);
        $this->assertEquals(0, (float) $pedido->doacao);
        // O que entra na gaveta por esta senha: -0,50 (saiu dinheiro)
        $this->assertEquals(-0.5, round((float) $pedido->total + (float) $pedido->caucao_cobrada - (float) $pedido->caucao_descontada + (float) $pedido->doacao, 2));
        $this->assertSame(1, CaucaoDevolucao::where('modo', 'bebidas')->count());
    }

    public function test_cliente_pode_doar_o_resto(): void
    {
        $categoria = Categoria::create(['nome' => 'Bebidas', 'secao' => 'bebidas']);
        $metro = Produto::create(['categoria_id' => $categoria->id, 'nome' => 'Metro', 'preco' => 12, 'caucao' => 3, 'disponivel' => true, 'disponivel_bar' => true]);
        $imperial = Produto::create(['categoria_id' => $categoria->id, 'nome' => 'Imperial', 'preco' => 2.5, 'disponivel' => true, 'disponivel_bar' => true]);

        $this->withSession($this->sessao())->post(route('pos.prepago.store'), [
            'items' => [['produto_id' => $imperial->id, 'quantidade' => 1]],
            'devolvidos' => [['produto_id' => $metro->id, 'quantidade' => 1]],
            'valor_recebido' => 0,
            'troco' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(0.5, (float) Pedido::firstOrFail()->doacao);
    }
}
