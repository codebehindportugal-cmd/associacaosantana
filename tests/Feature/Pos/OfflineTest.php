<?php

namespace Tests\Feature\Pos;

use App\Models\CaixaDiaria;
use App\Models\CaucaoDevolucao;
use App\Models\Categoria;
use App\Models\Impressora;
use App\Models\Pedido;
use App\Models\PosSession;
use App\Models\Produto;
use App\Services\CaixaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineTest extends TestCase
{
    use RefreshDatabase;

    private PosSession $posto;

    private Produto $imperial;

    private Produto $metro;

    private CaixaDiaria $caixa;

    protected function setUp(): void
    {
        parent::setUp();
        $usb = Impressora::create(['nome' => 'USB B', 'secao' => 'bar', 'tipo' => Impressora::TIPO_WEBUSB, 'ativa' => true]);
        $this->posto = PosSession::create(['nome' => 'Bar Longe', 'pin' => '1234', 'localizacao' => 'Bar Longe', 'tipo' => 'bar', 'ativo' => true, 'offline' => true, 'prefixo_senha' => 'B', 'impressora_id' => $usb->id]);
        $cat = Categoria::create(['nome' => 'Bebidas', 'secao' => 'bebidas']);
        $this->imperial = Produto::create(['categoria_id' => $cat->id, 'nome' => 'Imperial', 'preco' => 1.5, 'disponivel' => true, 'disponivel_bar' => true, 'gerir_stock' => true, 'stock_atual' => 3]);
        $this->metro = Produto::create(['categoria_id' => $cat->id, 'nome' => 'Metro', 'preco' => 12, 'caucao' => 5, 'disponivel' => true, 'disponivel_bar' => true]);
        $this->travel(-1)->hours();
        $this->caixa = CaixaDiaria::create(['data' => today(), 'ponto' => 'Bar Longe', 'fundo_maneio' => 50, 'estado' => 'aberta']);
        $this->travelBack();
    }

    private function sessao(): array
    {
        return ['pos_id' => $this->posto->id, 'pos_nome' => 'Bar Longe', 'pos_tipo' => 'bar', 'pos_localizacao' => 'Bar Longe', 'pos_operador' => 'Ana'];
    }

    private function venda(array $extra = []): array
    {
        return array_merge([
            'tipo' => 'venda',
            'uuid' => (string) Str::uuid(),
            'pos_id' => $this->posto->id,
            'ponto' => 'Bar Longe',
            'prefixo' => 'B',
            'caixa_id' => $this->caixa->id,
            'numero' => 1,
            'criado_em' => now()->subMinutes(10)->toIso8601String(),
            'items' => [['produto_id' => $this->imperial->id, 'quantidade' => 4, 'ja_tem' => 0, 'preco' => 1.5]],
            'devolvidos' => [],
            'total' => 6, 'caucao_cobrada' => 0, 'caucao_descontada' => 0,
            'valor_recebido' => 10, 'troco' => 4, 'doacao' => 0,
            'metodo' => 'dinheiro', 'juntar' => ['cozinha' => true], 'operador' => 'Ana',
        ], $extra);
    }

    private function enviar(array $eventos)
    {
        return $this->withSession($this->sessao())->postJson(route('pos.offline.enviar'), ['eventos' => $eventos])->assertOk();
    }

    public function test_dados_trazem_produtos_esgotados_stock_e_ultimo_numero(): void
    {
        $this->imperial->update(['stock_atual' => 0]);
        $this->enviar([$this->venda(['numero' => 7])]);

        $this->withSession($this->sessao())->getJson(route('pos.offline.dados'))
            ->assertOk()
            ->assertJsonPath('posto.prefixo', 'B')
            ->assertJsonPath('posto.offline', true)
            ->assertJsonPath('caixa.id', $this->caixa->id)
            ->assertJsonPath('ultimo_numero', 7)
            ->assertJsonPath('produtos.0.nome', 'Imperial')
            ->assertJsonPath('produtos.0.stock_atual', 0);
    }

    public function test_venda_offline_entra_uma_so_vez_com_letra_e_desconta_stock_sem_recusar(): void
    {
        $venda = $this->venda(['numero' => 12]);

        $res = $this->enviar([$venda])->json('resultados');
        $this->assertTrue($res[0]['ok']);
        $this->enviar([$venda]); // reenvio (rede caiu a meio da resposta)

        $this->assertSame(1, Pedido::count());
        $pedido = Pedido::with('items')->first();
        $this->assertSame('B-12', $pedido->codigo_senha);
        $this->assertEquals(6, (float) $pedido->total);
        $this->assertSame('Ana', $pedido->operador_nome);
        $this->assertSame(['cozinha' => true], $pedido->juntar);
        $this->assertEquals(0, (float) $this->imperial->fresh()->stock_atual, 'Vendeu 4 com 3 em stock: fica a zero, nao recusa');

        $resumo = app(CaixaService::class)->resumo($this->caixa->fresh());
        $this->assertEquals(56, $resumo['esperado_caixa']);
    }

    public function test_hora_do_posto_atrasada_conta_na_caixa(): void
    {
        $this->enviar([$this->venda(['criado_em' => now()->subHours(5)->toIso8601String()])]);
        $this->assertTrue(Pedido::first()->created_at->gte($this->caixa->created_at));
        $this->assertSame(1, app(CaixaService::class)->resumo($this->caixa->fresh())['pedidos']);
    }

    public function test_anulacao_espera_pela_venda_e_depois_tira_das_contas(): void
    {
        $venda = $this->venda(['caucao_descontada' => 5, 'devolvidos' => [['produto_id' => $this->metro->id, 'quantidade' => 1, 'caucao' => 5]], 'valor_recebido' => 1, 'troco' => 0]);
        $anulacao = ['tipo' => 'anulacao', 'pos_id' => $this->posto->id, 'uuid' => (string) Str::uuid(), 'venda_uuid' => $venda['uuid'], 'motivo' => 'Engano', 'criado_em' => now()->toIso8601String(), 'operador' => 'Ana'];

        $res = $this->enviar([$anulacao])->json('resultados');
        $this->assertFalse($res[0]['ok']);
        $this->assertFalse($res[0]['definitivo']);

        $res = $this->enviar([$venda, $anulacao])->json('resultados');
        $this->assertTrue($res[0]['ok'] && $res[1]['ok']);

        $pedido = Pedido::first();
        $this->assertSame('cancelado', $pedido->estado);
        $this->assertEquals(6, (float) $pedido->valor_devolvido); // 1 pago + 5 de caucao em dinheiro
        $this->assertSame('dinheiro', CaucaoDevolucao::sole()->modo);

        $resumo = app(CaixaService::class)->resumo($this->caixa->fresh());
        $this->assertSame(0, $resumo['pedidos']);
        $this->assertEquals(45, $resumo['esperado_caixa']); // 50 de fundo - 5 da caucao devolvida em dinheiro
    }

    public function test_devolucao_de_caucao_em_dinheiro_offline(): void
    {
        $evento = ['tipo' => 'caucao', 'pos_id' => $this->posto->id, 'ponto' => 'Bar Longe', 'caixa_id' => $this->caixa->id, 'uuid' => (string) Str::uuid(), 'produto_id' => $this->metro->id, 'quantidade' => 2, 'caucao' => 5, 'criado_em' => now()->toIso8601String(), 'operador' => 'Ana'];
        $this->enviar([$evento]);
        $this->enviar([$evento]);

        $this->assertSame(1, CaucaoDevolucao::count());
        $this->assertEquals(10, (float) CaucaoDevolucao::sole()->valor_total);
        $this->assertEquals(40, app(CaixaService::class)->resumo($this->caixa->fresh())['esperado_caixa']);
    }

    public function test_evento_impossivel_e_recusado_sem_parar_os_outros(): void
    {
        $ma = $this->venda(['items' => [['produto_id' => 99999, 'quantidade' => 1, 'preco' => 1]]]);
        $boa = $this->venda(['numero' => 2]);

        $res = $this->enviar([$ma, $boa])->json('resultados');
        $this->assertFalse($res[0]['ok']);
        $this->assertTrue($res[0]['definitivo']);
        $this->assertTrue($res[1]['ok']);
        $this->assertSame(1, Pedido::count());
    }

    public function test_posto_offline_precisa_de_letra_unica_e_de_ser_do_bar(): void
    {
        $admin = \App\Models\User::factory()->create();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('terminais.store'), ['nome' => 'X', 'tipo' => 'bar', 'pin' => '1234', 'offline' => true])
            ->assertSessionHasErrors('prefixo_senha');
        $this->actingAs($admin)->post(route('terminais.store'), ['nome' => 'X', 'tipo' => 'bar', 'pin' => '1234', 'offline' => true, 'prefixo_senha' => 'B'])
            ->assertSessionHasErrors('prefixo_senha');
        $this->actingAs($admin)->post(route('terminais.store'), ['nome' => 'X', 'tipo' => 'restaurante', 'pin' => '1234', 'offline' => true, 'prefixo_senha' => 'C'])
            ->assertSessionHasErrors('offline');
        $this->actingAs($admin)->post(route('terminais.store'), ['nome' => 'X', 'tipo' => 'cafe', 'pin' => '1234', 'offline' => true, 'prefixo_senha' => 'C'])
            ->assertSessionHasErrors('offline'); // sem impressora USB pelo browser
        $this->actingAs($admin)->post(route('terminais.store'), ['nome' => 'X', 'tipo' => 'cafe', 'pin' => '1234', 'offline' => true, 'prefixo_senha' => 'C', 'impressora_id' => $this->posto->impressora_id])
            ->assertSessionHasNoErrors();
    }
    public function test_registo_de_outro_posto_fica_a_espera_e_nao_entra_no_posto_errado(): void
    {
        $res = $this->enviar([$this->venda(['pos_id' => $this->posto->id + 99])])->json('resultados');
        $this->assertFalse($res[0]['ok']);
        $this->assertFalse($res[0]['definitivo']);
        $this->assertSame(0, Pedido::count());
    }

    public function test_venda_de_uma_caixa_fechada_fica_nessa_caixa_e_acerta_a_diferenca(): void
    {
        $venda = $this->venda(['criado_em' => now()->subMinutes(30)->toIso8601String()]); // 6 EUR a dinheiro na caixa 1

        // A caixa 1 foi fechada no backoffice (contaram 56) e abriu-se a caixa 2, com a venda ainda no posto
        $this->caixa->update(['estado' => 'fechada', 'valor_contado' => 56, 'diferenca' => 6, 'fechado_at' => now()->subMinutes(5)]);
        $caixa2 = CaixaDiaria::create(['data' => today()->addDay(), 'ponto' => 'Bar Longe', 'fundo_maneio' => 20, 'estado' => 'aberta']);

        $this->enviar([$venda]);

        $this->assertTrue(Pedido::first()->created_at->lte($this->caixa->fechado_at));
        $this->assertEquals(0, (float) $this->caixa->fresh()->diferenca, 'Com a venda que faltava, a caixa 1 bate certo');
        $this->assertStringContainsString('recalculada', $this->caixa->fresh()->observacoes_fecho);
        $this->assertSame(0, app(CaixaService::class)->resumo($caixa2)['pedidos'], 'Nao conta na caixa 2');
    }

    public function test_relogio_do_posto_atrasado_e_acertado(): void
    {
        // Relogio do posto 3 dias atrasado: a venda "aconteceu" ha 10 min no relogio do servidor
        $atraso = now()->subDays(3);
        $this->withSession($this->sessao())->postJson(route('pos.offline.enviar'), [
            'enviado_em' => $atraso->toIso8601String(),
            'eventos' => [$this->venda(['criado_em' => $atraso->copy()->subMinutes(10)->toIso8601String()])],
        ])->assertOk();

        $this->assertTrue(Pedido::first()->created_at->between(now()->subMinutes(11), now()->subMinutes(9)));
    }

    public function test_contas_que_nao_batem_entram_mas_ficam_assinaladas_e_total_negativo_e_recusado(): void
    {
        $this->enviar([$this->venda(['total' => 5])]);
        $this->assertStringContainsString('diferente da soma', Pedido::first()->observacoes);

        $res = $this->enviar([$this->venda(['total' => -500])])->json('resultados');
        $this->assertTrue($res[0]['definitivo']);
    }

    public function test_numero_repetido_entra_mas_fica_assinalado(): void
    {
        $this->enviar([$this->venda(['numero' => 3]), $this->venda(['numero' => 3])]);
        $this->assertSame(2, Pedido::count());
        $this->assertStringContainsString('repetido', Pedido::latest('id')->first()->observacoes);
    }

    public function test_nao_anula_sem_internet_uma_senha_de_outro_posto(): void
    {
        $outro = PosSession::create(['nome' => 'Outro', 'pin' => '1234', 'localizacao' => 'Bar Longe', 'tipo' => 'bar', 'ativo' => true]);
        $pedido = Pedido::create(['uuid' => (string) Str::uuid(), 'pos_id' => $outro->id, 'tipo' => 'bar_prepago', 'estado' => 'pronto', 'pago_antecipado' => true, 'ponto_bar' => 'Bar Longe', 'total' => 3]);

        $res = $this->enviar([['tipo' => 'anulacao', 'pos_id' => $this->posto->id, 'uuid' => (string) Str::uuid(), 'venda_uuid' => $pedido->uuid, 'motivo' => 'Engano', 'criado_em' => now()->toIso8601String()]])->json('resultados');
        $this->assertTrue($res[0]['definitivo']);
        $this->assertSame('pronto', $pedido->fresh()->estado);
    }
    public function test_venda_feita_depois_do_fecho_nao_entra_como_falta_na_caixa_fechada(): void
    {
        $this->caixa->update(['estado' => 'fechada', 'valor_contado' => 50, 'diferenca' => 0, 'fechado_at' => now()->subMinutes(10)]);

        // Sem caixa aberta a essa hora: fica fora de caixa e assinalada
        $this->enviar([$this->venda(['criado_em' => now()->subMinutes(5)->toIso8601String()])]);
        $this->assertEquals(0, (float) $this->caixa->fresh()->diferenca, 'A caixa fechada continua certa');
        $this->assertStringContainsString('depois do fecho', Pedido::first()->observacoes);

        // Com uma caixa nova aberta a essa hora: conta nessa
        $this->travel(-4)->minutes();
        $caixa2 = CaixaDiaria::create(['data' => today()->addDay(), 'ponto' => 'Bar Longe', 'fundo_maneio' => 20, 'estado' => 'aberta']);
        $this->travelBack();
        $this->enviar([$this->venda(['numero' => 2, 'criado_em' => now()->subMinutes(2)->toIso8601String()])]);

        $this->assertEquals(0, (float) $this->caixa->fresh()->diferenca);
        $this->assertSame(1, app(CaixaService::class)->resumo($caixa2->fresh())['pedidos']);
    }
}
