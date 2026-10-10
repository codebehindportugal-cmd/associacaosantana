<?php

namespace Tests\Feature\Pos;

use App\Models\PosSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class JuntarPadraoTest extends TestCase
{
    use RefreshDatabase;

    public function test_posto_guarda_e_recebe_o_que_junta_por_omissao(): void
    {
        $pos = PosSession::create(['nome' => 'Bar 1', 'pin' => '1234', 'localizacao' => 'Bar', 'tipo' => 'bar', 'ativo' => true]);
        $outro = PosSession::create(['nome' => 'Bar 2', 'pin' => '1234', 'localizacao' => 'Bar 2', 'tipo' => 'bar', 'ativo' => true]);
        $sessao = fn ($p) => ['pos_id' => $p->id, 'pos_nome' => $p->nome, 'pos_tipo' => 'bar', 'pos_localizacao' => $p->localizacao];

        $this->withSession($sessao($pos))
            ->post(route('pos.juntar-padrao'), ['juntar' => ['cozinha' => 1, 'sobremesas' => 0, 'bebidas' => 0, 'outra' => 1]])
            ->assertSessionHasNoErrors();

        $this->assertSame(['cozinha' => true, 'sobremesas' => false, 'bebidas' => false], $pos->fresh()->juntar_padrao);

        $this->withSession($sessao($pos))->get(route('pos.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('juntarPadrao.cozinha', true)->where('juntarPadrao.bebidas', false));

        // Outro posto continua com tudo separado
        $this->withSession($sessao($outro))->get(route('pos.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('juntarPadrao.cozinha', false));
    }
}
