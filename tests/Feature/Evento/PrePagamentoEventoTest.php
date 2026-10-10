<?php

namespace Tests\Feature\Evento;

use App\Models\CaixaDiaria;
use App\Models\Categoria;
use App\Models\Configuracao;
use App\Models\Impressora;
use App\Models\Pedido;
use App\Models\PosSession;
use App\Models\PrintJob;
use App\Models\Produto;
use App\Models\TalaoConfig;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Ensaio do evento: 3 postos de pre-pagamento a vender ao mesmo tempo,
 * cada um com a sua impressora (pelo Raspberry) e a sua caixa.
 *
 * Correr:  php artisan test --configuration=phpunit.evento.xml
 */
class PrePagamentoEventoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var array<int, PosSession> */
    private array $postos = [];

    private Produto $imperial;
    private Produto $metro;
    private Produto $bifana;
    private Produto $frango;
    private Produto $batata;
    private Produto $baba;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        TalaoConfig::query()->update(['em_uso' => false]);
        TalaoConfig::create([
            'nome' => 'Evento',
            'titulo' => 'Carvalhal Fest',
            'rodape' => 'Obrigado',
            'prepago_apenas_individuais' => true,
            'ativo' => true,
            'em_uso' => true,
        ]);
        TalaoConfig::esquecer();

        $bebidas = Categoria::firstOrCreate(['nome' => 'Bebidas'], ['secao' => 'bebidas']);
        $comida = Categoria::firstOrCreate(['nome' => 'Comida'], ['secao' => 'comida']);
        $frango = Categoria::firstOrCreate(['nome' => 'Frango'], ['secao' => 'frango']);
        $acomp = Categoria::firstOrCreate(['nome' => 'Acompanhamentos'], ['secao' => 'acompanhamentos']);
        $sobremesas = Categoria::firstOrCreate(['nome' => 'Sobremesas'], ['secao' => 'sobremesas']);

        $this->imperial = $this->produto($bebidas, 'Imperial', 1.50);
        $this->metro = $this->produto($bebidas, 'Metro de Imperial', 12.00, caucao: 5.00);
        $this->bifana = $this->produto($comida, 'Bifana', 3.50);
        $this->frango = $this->produto($frango, 'Frango', 8.00);
        $this->batata = $this->produto($acomp, 'Batata Frita', 2.00);
        $this->baba = $this->produto($sobremesas, 'Baba de Camelo', 2.50);

        // 3 postos, cada um com a sua impressora de rede pelo Raspberry
        foreach ([1 => '10.0.0.201', 2 => '10.0.0.202', 3 => '10.0.0.203'] as $n => $ip) {
            $impressora = Impressora::create([
                'nome' => "Impressora Pre $n",
                'secao' => 'cafe',
                'tipo' => Impressora::TIPO_REDE,
                'host' => $ip,
                'porta' => 9100,
                'ativa' => true,
            ]);

            $this->postos[$n] = PosSession::create([
                'nome' => $n === 1 ? 'Pre pagamento' : "Pre pagamento $n",
                'pin' => '1234',
                'localizacao' => $n === 1 ? 'Pre Pagamento' : "Pre Pagamento $n",
                'tipo' => 'cafe',
                'impressora_id' => $impressora->id,
                'ativo' => true,
            ]);
        }
    }

    // ------------------------------------------------------------------
    //  Senhas
    // ------------------------------------------------------------------

    public function test_os_tres_postos_nunca_repetem_o_numero_de_senha(): void
    {
        $this->abrirCaixas();

        for ($i = 0; $i < 30; $i++) {
            $this->vender($this->postos[($i % 3) + 1], [[$this->imperial, 1]])->assertRedirect();
        }

        $senhas = Pedido::where('tipo', 'bar_prepago')->pluck('numero_senha');

        $this->assertCount(30, $senhas);
        $this->assertCount(30, $senhas->unique(), 'Ha senhas repetidas entre postos');
        $this->assertEquals(range(1, 30), $senhas->sort()->values()->all());

        // Cada posto vendeu 10
        foreach ($this->postos as $posto) {
            $this->assertSame(10, Pedido::where('ponto_bar', $posto->localizacao)->count());
        }
    }

    public function test_o_prepago_do_backoffice_partilha_a_mesma_contagem_de_senhas(): void
    {
        $this->abrirCaixas();

        $this->vender($this->postos[1], [[$this->imperial, 1]]);
        $this->actingAs($this->admin)->post(route('bar.store-prepago'), [
            'ponto_bar' => $this->postos[2]->localizacao,
            'valor_recebido' => 10,
            'items' => [['produto_id' => $this->imperial->id, 'quantidade' => 1]],
        ])->assertRedirect();
        $this->vender($this->postos[3], [[$this->imperial, 1]]);

        $this->assertEquals([1, 2, 3], Pedido::orderBy('id')->pluck('numero_senha')->all());
    }

    public function test_abrir_a_segunda_e_terceira_caixa_a_meio_nao_recomeca_as_senhas(): void
    {
        $this->abrirCaixa($this->postos[1]);
        $this->vender($this->postos[1], [[$this->imperial, 1]]);
        $this->vender($this->postos[1], [[$this->imperial, 1]]);

        $this->abrirCaixa($this->postos[2]);
        $this->abrirCaixa($this->postos[3]);

        $this->vender($this->postos[2], [[$this->imperial, 1]]);
        $this->vender($this->postos[3], [[$this->imperial, 1]]);

        $this->assertEquals([1, 2, 3, 4], Pedido::orderBy('id')->pluck('numero_senha')->all());
    }

    public function test_fechar_todas_as_caixas_a_meio_da_noite_e_reabrir_nao_repete_senhas(): void
    {
        $this->abrirCaixas();
        $this->vender($this->postos[1], [[$this->imperial, 1]]);
        $this->vender($this->postos[2], [[$this->imperial, 1]]);

        // Ex.: troca de turno — fecham-se as 3 para contar e voltam a abrir
        foreach (CaixaDiaria::aberta()->get() as $caixa) {
            $this->actingAs($this->admin)->patch(route('caixa.fechar', $caixa), ['valor_contado' => 50])->assertRedirect();
        }
        $this->travel(20)->minutes();
        $this->abrirCaixas();

        $this->vender($this->postos[3], [[$this->imperial, 1]]);

        $this->assertEquals([1, 2, 3], Pedido::orderBy('id')->pluck('numero_senha')->all(),
            'Reabrir as caixas a meio do evento recomecou as senhas: ia haver numeros repetidos nas maos dos clientes');
    }

    public function test_no_dia_seguinte_as_senhas_recomecam_no_1(): void
    {
        $this->abrirCaixas();
        $this->vender($this->postos[1], [[$this->imperial, 1]]);
        $this->vender($this->postos[2], [[$this->imperial, 1]]);

        foreach (CaixaDiaria::aberta()->get() as $caixa) {
            $this->actingAs($this->admin)->patch(route('caixa.fechar', $caixa), ['valor_contado' => 50]);
        }

        $this->travelTo(Carbon::tomorrow()->setTime(18, 0));
        $this->abrirCaixas();
        $this->vender($this->postos[1], [[$this->imperial, 1]]);

        $this->assertSame(1, (int) Pedido::latest('id')->value('numero_senha'));
    }

    // ------------------------------------------------------------------
    //  Venda
    // ------------------------------------------------------------------

    public function test_com_a_caixa_fechada_o_posto_nao_vende(): void
    {
        $this->abrirCaixa($this->postos[1]);

        $this->vender($this->postos[2], [[$this->imperial, 1]])->assertSessionHasErrors('ponto_bar');

        $this->assertSame(0, Pedido::count());
        $this->assertSame(0, PrintJob::count());
    }

    public function test_valor_recebido_abaixo_do_total_e_recusado(): void
    {
        $this->abrirCaixas();

        $this->vender($this->postos[1], [[$this->frango, 1]], recebido: 5)->assertSessionHasErrors('valor_recebido');

        $this->assertSame(0, Pedido::count());
        $this->assertSame(0, (int) Configuracao::where('chave', 'ultima_senha_bar')->value('valor'),
            'Uma venda recusada nao pode gastar um numero de senha');
    }

    public function test_produto_indisponivel_no_bar_e_recusado(): void
    {
        $this->abrirCaixas();
        $this->imperial->update(['disponivel_bar' => false]);

        $this->vender($this->postos[1], [[$this->imperial, 1]])->assertSessionHasErrors('items.0.produto_id');
        $this->assertSame(0, Pedido::count());
    }

    public function test_uma_falha_no_registo_de_auditoria_nao_impede_a_venda(): void
    {
        $this->abrirCaixas();
        \Illuminate\Support\Facades\Schema::rename('audit_logs', 'audit_logs_fora');

        try {
            $this->vender($this->postos[1], [[$this->imperial, 2]])->assertSessionHasNoErrors()->assertRedirect();
        } finally {
            \Illuminate\Support\Facades\Schema::rename('audit_logs_fora', 'audit_logs');
        }

        $this->assertSame(1, Pedido::count());
        $this->assertSame(3, PrintJob::count());
    }

    // ------------------------------------------------------------------
    //  Impressao
    // ------------------------------------------------------------------

    public function test_cada_posto_imprime_na_sua_impressora_uma_senha_por_unidade_e_a_conta_no_fim(): void
    {
        $this->abrirCaixas();

        $this->vender($this->postos[2], [[$this->bifana, 2], [$this->imperial, 1]]);

        $jobs = PrintJob::orderBy('id')->get();

        $this->assertCount(4, $jobs, '3 senhas (1 por unidade) + 1 conta');
        $this->assertSame([$this->postos[2]->impressora_id], $jobs->pluck('impressora_id')->unique()->values()->all(),
            'Os taloes sairam numa impressora que nao e a do posto');

        $subtitulos = $jobs->map(fn ($j) => $j->payload['subtitulo'] ?? null)->all();
        $this->assertSame('CONTA', end($subtitulos), 'A conta tem de sair no fim');

        $this->assertTrue((bool) ($jobs->first()->payload['abrir_caixa'] ?? false), 'A gaveta abre com o primeiro talao');
        $this->assertFalse((bool) ($jobs->last()->payload['abrir_caixa'] ?? false));

        $senha = Pedido::first()->numero_senha;
        foreach ($jobs->take(3) as $job) {
            $this->assertStringContainsString('SENHA #'.$senha, $this->texto($job));
        }
    }

    public function test_juntar_por_seccao_comida_frango_e_acompanhamentos_numa_folha_resto_separado(): void
    {
        $this->abrirCaixas();

        $this->vender($this->postos[1], [
            [$this->bifana, 2], [$this->frango, 1], [$this->batata, 1], [$this->baba, 2], [$this->imperial, 3],
        ], juntar: ['cozinha' => 1, 'sobremesas' => 0, 'bebidas' => 0]);

        $jobs = PrintJob::orderBy('id')->get();
        // 1 folha cozinha + 2 sobremesas + 3 bebidas + conta
        $this->assertCount(7, $jobs);

        $folha = $this->texto($jobs[0]);
        foreach (['COMIDA', 'FRANGO', 'ACOMPANHAMENTOS', 'Bifana', 'Frango', 'Batata Frita'] as $esperado) {
            $this->assertStringContainsString($esperado, $folha);
        }
        $this->assertStringContainsString('2x Bifana', $folha, 'Produtos iguais juntos numa linha na folha junta');
        $this->assertStringNotContainsString('1x Bifana', $folha);
        $this->assertStringNotContainsString('Baba', $folha);
        $this->assertSame('CONTA', $jobs->last()->payload['subtitulo']);
    }

    public function test_juntar_bebidas_a_pedido_do_cliente(): void
    {
        $this->abrirCaixas();

        $this->vender($this->postos[3], [[$this->imperial, 3], [$this->bifana, 1]], juntar: ['bebidas' => 1]);

        // 1 folha bebidas + 1 senha bifana + conta
        $this->assertSame(3, PrintJob::count());
    }

    public function test_sem_juntar_tudo_sai_separado_por_omissao(): void
    {
        $this->abrirCaixas();

        $this->vender($this->postos[1], [[$this->imperial, 3], [$this->bifana, 2], [$this->baba, 1]]);

        $this->assertSame(7, PrintJob::count(), '6 senhas + conta');
    }

    // ------------------------------------------------------------------
    //  Caucao do metro
    // ------------------------------------------------------------------

    public function test_metro_novo_cobra_caucao_e_encher_nao_cobra(): void
    {
        $this->abrirCaixas();

        // Novo
        $this->vender($this->postos[1], [[$this->metro, 1]], recebido: 17)->assertSessionHasNoErrors();
        // Encher: o cliente ja tem o metro
        $this->vender($this->postos[1], [[$this->metro, 1, 1]], recebido: 12)->assertSessionHasNoErrors();

        [$novo, $encher] = Pedido::orderBy('id')->get();

        $this->assertEquals(5.00, (float) $novo->caucao_cobrada);
        $this->assertEquals(0.00, (float) $encher->caucao_cobrada, 'Quem vem encher nao pode pagar outra caucao');
        $this->assertEquals(12.00, (float) $encher->total);
    }

    public function test_metro_devolvido_desconta_na_senha_e_dinheiro_sai_da_gaveta(): void
    {
        $this->abrirCaixas();

        $this->vender($this->postos[1], [[$this->metro, 1]], recebido: 17);

        // Devolve o metro e usa o saldo em imperiais (5 EUR -> 4 imperiais = 6 EUR, paga 1)
        $this->vender($this->postos[2], [[$this->imperial, 4]], recebido: 1, devolvidos: [[$this->metro, 1]])->assertSessionHasNoErrors();
        $this->assertEquals(5.00, (float) Pedido::latest('id')->value('caucao_descontada'));

        // Saldo maior do que a senha: o resto (5 - 1,50 = 3,50) sai da gaveta como troco
        $this->vender($this->postos[2], [[$this->imperial, 1]], recebido: 0, troco: 3.50, devolvidos: [[$this->metro, 1]])
            ->assertSessionHasNoErrors();
        $this->assertEquals(3.50, (float) Pedido::latest('id')->value('troco'));

        // Devolucao em dinheiro
        $this->withSession($this->sessao($this->postos[3]))
            ->post(route('pos.caucao.devolver'), ['produto_id' => $this->metro->id, 'quantidade' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('caucao_devolucoes', ['modo' => 'dinheiro', 'ponto' => $this->postos[3]->localizacao, 'valor_total' => 5.00]);
    }

    // ------------------------------------------------------------------
    //  Caixa
    // ------------------------------------------------------------------

    public function test_fecho_de_caixa_bate_com_o_dinheiro_na_gaveta_em_cada_posto(): void
    {
        $this->abrirCaixas(fundo: 50);

        // Posto 1: imperial 1,50 paga com 2 e o cliente deixa o troco (0,50 de doacao)
        $this->vender($this->postos[1], [[$this->imperial, 1]], recebido: 2, troco: 0);
        // Posto 1: metro novo 12 + 5 caucao, paga 20, troco 3
        $this->vender($this->postos[1], [[$this->metro, 1]], recebido: 20, troco: 3);
        // Posto 2: frango 8, paga certo
        $this->vender($this->postos[2], [[$this->frango, 1]], recebido: 8);
        // Posto 3: metro devolvido em dinheiro (sai 5 da gaveta)
        $this->withSession($this->sessao($this->postos[3]))
            ->post(route('pos.caucao.devolver'), ['produto_id' => $this->metro->id, 'quantidade' => 1]);

        $gaveta = [
            1 => 50 + 2 + (20 - 3),
            2 => 50 + 8,
            3 => 50 - 5,
        ];

        $caixas = collect($this->actingAs($this->admin)->get(route('caixa.index'))->viewData('page')['props']['caixas'])
            ->keyBy('ponto');

        foreach ($gaveta as $n => $dinheiro) {
            $this->assertEquals($dinheiro, $caixas[$this->postos[$n]->localizacao]['esperado_caixa'],
                "O esperado na caixa do posto $n nao bate com o dinheiro na gaveta");
        }

        $this->assertEquals(13.50, $caixas[$this->postos[1]->localizacao]['vendas'], 'A caucao nao e venda');
    }

    // ------------------------------------------------------------------
    //  Forma de pagamento
    // ------------------------------------------------------------------

    public function test_mbway_e_contactless_nao_abrem_a_gaveta_nem_tem_troco(): void
    {
        $this->abrirCaixas();

        foreach (['mbway' => 'MB WAY', 'contactless' => 'Contactless'] as $metodo => $nome) {
            PrintJob::query()->delete();

            // Mesmo que venha um valor recebido maior (ecra ainda com o valor do dinheiro), cobra o certo
            $this->vender($this->postos[1], [[$this->metro, 1]], recebido: 50, troco: 0, metodo: $metodo)->assertSessionHasNoErrors();

            $pedido = Pedido::latest('id')->first();
            $this->assertSame($metodo, $pedido->metodo_pagamento);
            $this->assertEquals(17.00, (float) $pedido->valor_recebido);
            $this->assertEquals(0.00, (float) $pedido->troco);
            $this->assertEquals(0.00, (float) $pedido->doacao);

            $jobs = PrintJob::orderBy('id')->get();
            $this->assertFalse($jobs->contains(fn ($j) => (bool) ($j->payload['abrir_caixa'] ?? false)), "$nome nao pode abrir a gaveta");

            $conta = $this->texto($jobs->last());
            $this->assertStringContainsString('Pagamento: '.$nome, $conta);
            $this->assertStringNotContainsString('Troco', $conta);
        }
    }

    public function test_dinheiro_abre_a_gaveta_e_mostra_o_troco_na_conta(): void
    {
        $this->abrirCaixas();

        $this->vender($this->postos[1], [[$this->imperial, 1]], recebido: 5, troco: 3.50);

        $jobs = PrintJob::orderBy('id')->get();
        $this->assertTrue((bool) ($jobs->first()->payload['abrir_caixa'] ?? false));
        $conta = $this->texto($jobs->last());
        $this->assertStringContainsString('Pagamento: Dinheiro', $conta);
        $this->assertStringContainsString('Troco: 3,50 EUR', $conta);
    }

    public function test_forma_de_pagamento_desconhecida_e_recusada(): void
    {
        $this->abrirCaixas();

        $this->vender($this->postos[1], [[$this->imperial, 1]], metodo: 'cheque')->assertSessionHasErrors('metodo_pagamento');
        $this->assertSame(0, Pedido::count());
    }

    public function test_na_caixa_so_o_dinheiro_conta_para_a_gaveta_e_mbway_contactless_aparecem_a_parte(): void
    {
        $this->abrirCaixas(fundo: 50);

        $this->vender($this->postos[1], [[$this->imperial, 2]], recebido: 5, troco: 2);              // 3 a dinheiro
        $this->vender($this->postos[1], [[$this->frango, 1]], metodo: 'mbway');                       // 8 MB WAY
        $this->vender($this->postos[1], [[$this->metro, 1]], metodo: 'contactless');                  // 12 + 5 caucao
        $this->vender($this->postos[1], [[$this->imperial, 4]], devolvidos: [[$this->metro, 1]], metodo: 'mbway'); // 6 - 5 = 1 MB WAY

        $caixa = collect($this->actingAs($this->admin)->get(route('caixa.index'))->viewData('page')['props']['caixas'])
            ->firstWhere('ponto', $this->postos[1]->localizacao);

        $this->assertEquals(50 + 3, $caixa['esperado_caixa'], 'So o dinheiro entra na gaveta');
        $this->assertEquals(3 + 8 + 12 + 6, $caixa['vendas']);
        $this->assertEquals(9.00, $caixa['por_metodo']['mbway']);
        $this->assertEquals(17.00, $caixa['por_metodo']['contactless']);
    }

    // ------------------------------------------------------------------
    //  Senhas anteriores: reimprimir e anular
    // ------------------------------------------------------------------

    public function test_lista_de_senhas_so_mostra_as_do_proprio_posto_e_procura_por_numero(): void
    {
        $this->abrirCaixas();
        $this->vender($this->postos[1], [[$this->imperial, 1]]);   // #1
        $this->vender($this->postos[2], [[$this->bifana, 1]]);     // #2
        $this->vender($this->postos[1], [[$this->frango, 1]]);     // #3

        $lista = $this->withSession($this->sessao($this->postos[1]))->getJson(route('pos.senhas'))->assertOk()->json('senhas');
        $this->assertEquals([3, 1], array_column($lista, 'numero'));
        $this->assertSame('Frango', $lista[0]['itens'][0]['nome']);

        $uma = $this->withSession($this->sessao($this->postos[1]))->getJson(route('pos.senhas', ['numero' => 1]))->json('senhas');
        $this->assertEquals([1], array_column($uma, 'numero'));
    }

    public function test_reimprimir_tudo_sai_com_2a_via_igual_ao_original_e_nao_abre_a_gaveta(): void
    {
        $this->abrirCaixas();
        $this->vender($this->postos[1], [[$this->bifana, 2], [$this->frango, 1], [$this->imperial, 1]], juntar: ['cozinha' => 1]);
        $pedido = Pedido::first();
        $originais = PrintJob::count();   // 1 folha cozinha + 1 imperial + conta = 3
        $this->assertSame(3, $originais);

        $this->withSession($this->sessao($this->postos[1]))
            ->post(route('pos.pedido.reimprimir', $pedido), ['o' => 'tudo'])->assertRedirect();

        $via = PrintJob::where('id', '>', PrintJob::orderBy('id')->skip($originais - 1)->value('id'))->orderBy('id')->get();
        $this->assertCount($originais, $via, 'A 2a via tem de sair igual (com as seccoes juntas como na venda)');
        foreach ($via as $job) {
            $this->assertStringContainsString('2a VIA', $this->texto($job));
            $this->assertFalse((bool) ($job->payload['abrir_caixa'] ?? false));
            $this->assertSame($this->postos[1]->impressora_id, $job->impressora_id);
        }
        $this->assertSame(1, $pedido->fresh()->reimpressoes);
    }

    public function test_reimprimir_so_a_conta(): void
    {
        $this->abrirCaixas();
        $this->vender($this->postos[1], [[$this->imperial, 3]]);
        $antes = PrintJob::count();

        $this->withSession($this->sessao($this->postos[1]))
            ->post(route('pos.pedido.reimprimir', Pedido::first()), ['o' => 'conta']);

        $this->assertSame($antes + 1, PrintJob::count());
        $this->assertSame('CONTA 2a VIA', PrintJob::latest('id')->first()->payload['subtitulo']);
    }

    public function test_anular_senha_a_dinheiro_tira_das_vendas_da_caixa_e_abre_a_gaveta_para_devolver(): void
    {
        $this->abrirCaixas(fundo: 50);
        $this->vender($this->postos[1], [[$this->frango, 1]], recebido: 10, troco: 2);      // fica
        $this->vender($this->postos[1], [[$this->bifana, 2]], recebido: 10, troco: 3);      // 7 -> anulada
        $anular = Pedido::latest('id')->first();

        // Ainda ha taloes desta senha por imprimir: ja nao podem sair
        $this->withSession($this->sessao($this->postos[1]))
            ->post(route('pos.pedido.anular', $anular), ['motivo' => 'Cliente desistiu'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $anular->refresh();
        $this->assertSame('cancelado', $anular->estado);
        $this->assertEquals(7.00, (float) $anular->valor_devolvido);
        $this->assertSame('Cliente desistiu', $anular->motivo_anulacao);

        $this->assertSame(0, PrintJob::where('printable_id', $anular->id)->where('estado', 'pendente')
            ->where('payload->subtitulo', '!=', 'ANULADA')->count(), 'Os taloes por imprimir da senha anulada nao podem sair');

        $talao = PrintJob::latest('id')->first();
        $this->assertSame('ANULADA', $talao->payload['subtitulo']);
        $this->assertTrue((bool) $talao->payload['abrir_caixa'], 'Abre a gaveta para devolver o dinheiro');
        $this->assertStringContainsString('Devolver: 7,00 EUR', $this->texto($talao));

        $caixa = collect($this->actingAs($this->admin)->get(route('caixa.index'))->viewData('page')['props']['caixas'])
            ->firstWhere('ponto', $this->postos[1]->localizacao);
        $this->assertEquals(8.00, $caixa['vendas'], 'A senha anulada nao conta nas vendas');
        $this->assertEquals(50 + 8, $caixa['esperado_caixa'], 'O dinheiro devolvido saiu da gaveta');

        // Anulada: nao se reimprime nem se anula outra vez
        $this->withSession($this->sessao($this->postos[1]))->post(route('pos.pedido.reimprimir', $anular), ['o' => 'tudo'])->assertStatus(422);
        $this->withSession($this->sessao($this->postos[1]))->post(route('pos.pedido.anular', $anular), ['motivo' => 'outra vez'])->assertSessionHasErrors('motivo');
    }

    public function test_anular_senha_mbway_nao_abre_a_gaveta(): void
    {
        $this->abrirCaixas();
        $this->vender($this->postos[1], [[$this->frango, 1]], metodo: 'mbway');

        $this->withSession($this->sessao($this->postos[1]))
            ->post(route('pos.pedido.anular', Pedido::first()), ['motivo' => 'Pagamento nao passou'])->assertSessionHasNoErrors();

        $this->assertFalse((bool) (PrintJob::latest('id')->first()->payload['abrir_caixa'] ?? false));
    }

    public function test_anular_senha_paga_com_metro_devolvido_devolve_a_caucao_em_dinheiro(): void
    {
        $this->abrirCaixas(fundo: 50);
        $this->vender($this->postos[1], [[$this->metro, 1]], recebido: 17);                                       // +17 gaveta
        $this->vender($this->postos[1], [[$this->imperial, 4]], recebido: 1, devolvidos: [[$this->metro, 1]]);   // +1 gaveta
        $anular = Pedido::latest('id')->first();

        $this->withSession($this->sessao($this->postos[1]))
            ->post(route('pos.pedido.anular', $anular), ['motivo' => 'Engano no pedido'])->assertSessionHasNoErrors();

        $this->assertEquals(6.00, (float) $anular->fresh()->valor_devolvido, '1 EUR pago + 5 EUR de caucao do metro');
        $this->assertDatabaseHas('caucao_devolucoes', ['pedido_id' => $anular->id, 'modo' => 'dinheiro']);

        $caixa = collect($this->actingAs($this->admin)->get(route('caixa.index'))->viewData('page')['props']['caixas'])
            ->firstWhere('ponto', $this->postos[1]->localizacao);
        $this->assertEquals(50 + 17 + 1 - 6, $caixa['esperado_caixa']);
    }

    public function test_nao_se_mexe_em_senhas_de_outro_posto_e_o_motivo_e_obrigatorio(): void
    {
        $this->abrirCaixas();
        $this->vender($this->postos[2], [[$this->imperial, 1]]);
        $pedido = Pedido::first();

        $this->withSession($this->sessao($this->postos[1]))->post(route('pos.pedido.reimprimir', $pedido), ['o' => 'tudo'])->assertNotFound();
        $this->withSession($this->sessao($this->postos[1]))->post(route('pos.pedido.anular', $pedido), ['motivo' => 'x'])->assertNotFound();
        $this->withSession($this->sessao($this->postos[2]))->post(route('pos.pedido.anular', $pedido), ['motivo' => ''])->assertSessionHasErrors('motivo');

        $this->assertSame('pronto', $pedido->fresh()->estado);
    }

    // ------------------------------------------------------------------
    //  Agente do Raspberry
    // ------------------------------------------------------------------

    public function test_agente_com_token_errado_e_recusado_e_o_backoffice_fica_a_saber(): void
    {
        config(['services.print_agent.token' => 'token-do-servidor-1234567890']);

        $this->getJson(route('print-agent.jobs'), ['Authorization' => 'Bearer errado'])->assertStatus(401);

        $props = $this->actingAs($this->admin)->get(route('impressoras.index'))->viewData('page')['props'];
        $this->assertNotNull($props['agente']['ultimo_401_at'] ?? null);
    }

    public function test_token_definido_no_site_passa_a_valer_e_o_antigo_deixa_de_servir(): void
    {
        config(['services.print_agent.token' => 'token-do-servidor-1234567890']);
        $this->getJson(route('print-agent.jobs'), ['Authorization' => 'Bearer token-do-servidor-1234567890'])->assertOk();

        $this->actingAs($this->admin)->post(route('impressoras.token-agente'), ['token' => 'token-do-raspberry-0987654321'])
            ->assertSessionHasNoErrors();

        $this->getJson(route('print-agent.jobs'), ['Authorization' => 'Bearer token-do-raspberry-0987654321'])->assertOk();
        $this->getJson(route('print-agent.jobs'), ['Authorization' => 'Bearer token-do-servidor-1234567890'])->assertStatus(401);
    }

    public function test_o_raspberry_leva_os_taloes_dos_3_postos_e_marca_como_impressos(): void
    {
        $token = 'token-do-raspberry-0987654321';
        $this->actingAs($this->admin)->post(route('impressoras.token-agente'), ['token' => $token]);
        $this->abrirCaixas();

        foreach ($this->postos as $posto) {
            $this->vender($posto, [[$this->imperial, 1]]);
        }

        $jobs = $this->getJson(route('print-agent.jobs'), ['Authorization' => "Bearer $token"])->assertOk()->json('jobs');

        $this->assertCount(6, $jobs, '3 postos x (1 senha + 1 conta)');
        $this->assertEqualsCanonicalizing(['10.0.0.201', '10.0.0.202', '10.0.0.203'],
            collect($jobs)->pluck('printer.host')->unique()->values()->all());

        foreach ($jobs as $job) {
            $this->postJson(route('print-agent.jobs.done', $job['id']), [], ['Authorization' => "Bearer $token"])->assertOk();
        }

        $this->assertSame(6, PrintJob::where('estado', 'impresso')->count());
        $this->assertSame([], $this->getJson(route('print-agent.jobs'), ['Authorization' => "Bearer $token"])->json('jobs') ?? []);
    }

    public function test_agente_com_nome_so_leva_as_impressoras_dele(): void
    {
        $token = 'token-do-raspberry-0987654321';
        $this->actingAs($this->admin)->post(route('impressoras.token-agente'), ['token' => $token]);
        Impressora::find($this->postos[3]->impressora_id)->update(['agente' => 'pi-tenda']);
        $this->abrirCaixas();

        foreach ($this->postos as $posto) {
            $this->vender($posto, [[$this->imperial, 1]]);
        }

        $semNome = $this->getJson(route('print-agent.jobs'), ['Authorization' => "Bearer $token"])->json('jobs');
        $tenda = $this->getJson(route('print-agent.jobs').'?agente=pi-tenda', ['Authorization' => "Bearer $token"])->json('jobs');

        $this->assertCount(4, $semNome);
        $this->assertCount(2, $tenda);
        $this->assertSame(['10.0.0.203'], collect($tenda)->pluck('printer.host')->unique()->values()->all());
    }

    public function test_o_raspberry_descarrega_a_versao_atual_do_agente_com_o_token_dele(): void
    {
        $token = 'token-do-raspberry-0987654321';
        $this->actingAs($this->admin)->post(route('impressoras.token-agente'), ['token' => $token]);

        $this->get(route('print-agent.codigo'), ['Authorization' => 'Bearer errado'])->assertStatus(401);

        $codigo = $this->get(route('print-agent.codigo'), ['Authorization' => "Bearer $token"])->assertOk()->getContent();
        $this->assertStringContainsString('imprimirFila', $codigo, 'Tem de servir o agente com uma fila por impressora');
        $this->assertSame(file_get_contents(base_path('local-printer-agent/agent.mjs')), $codigo);
    }

    public function test_taloes_antigos_nao_saem_quando_o_raspberry_volta(): void
    {
        $token = 'token-do-raspberry-0987654321';
        $this->actingAs($this->admin)->post(route('impressoras.token-agente'), ['token' => $token]);
        $this->abrirCaixas();
        $this->vender($this->postos[1], [[$this->imperial, 1]]);

        $this->travel(30)->minutes();

        $this->assertSame([], $this->getJson(route('print-agent.jobs'), ['Authorization' => "Bearer $token"])->json('jobs') ?? []);
    }

    // ------------------------------------------------------------------
    //  Ajudas
    // ------------------------------------------------------------------

    private function produto(Categoria $categoria, string $nome, float $preco, float $caucao = 0): Produto
    {
        return Produto::create([
            'categoria_id' => $categoria->id,
            'nome' => $nome,
            'preco' => $preco,
            'caucao' => $caucao,
            'disponivel' => true,
            'disponivel_bar' => true,
        ]);
    }

    public function test_fecho_de_caixa_imprime_talao_com_dinheiro_e_mbway(): void
    {
        $this->abrirCaixa($this->postos[1], 50);

        $this->vender($this->postos[1], [[$this->imperial, 2]])->assertSessionHasNoErrors();                    // 3,00 dinheiro
        $this->vender($this->postos[1], [[$this->imperial, 4]], metodo: 'mbway')->assertSessionHasNoErrors();   // 6,00 MB WAY
        $this->vender($this->postos[1], [[$this->metro, 1]])->assertSessionHasNoErrors();                       // 12 + 5 caucao

        $caixa = CaixaDiaria::where('ponto', $this->postos[1]->localizacao)->firstOrFail();
        PrintJob::query()->delete();

        // Leitura sem fechar: sai na impressora do posto
        $this->actingAs($this->admin)->post(route('caixa.imprimir', $caixa))->assertSessionHasNoErrors();
        $leitura = PrintJob::where('tipo', 'talao_caixa')->sole();
        $this->assertSame($this->postos[1]->impressora_id, $leitura->impressora_id);
        $this->assertSame('LEITURA DE CAIXA', $leitura->payload['subtitulo']);

        $this->actingAs($this->admin)->patch(route('caixa.fechar', $caixa), [
            'valor_contado' => 70,
            'contagem' => ['50' => 1, '10' => 2, '0.5' => 0],
        ])->assertSessionHasNoErrors();

        $caixa->refresh();
        $this->assertSame(['50' => 1, '10' => 2], $caixa->contagem);
        $this->assertEquals(0, (float) $caixa->diferenca); // 50 + 3 + 17 = 70

        $fecho = PrintJob::where('tipo', 'talao_caixa')->latest('id')->first();
        $this->assertSame('FECHO DE CAIXA', $fecho->payload['subtitulo']);
        $texto = collect($fecho->payload['linhas'])->map(fn ($l) => is_array($l) ? $l['texto'] : $l)->implode("\n");
        $this->assertMatchesRegularExpression('/^Dinheiro +20,00 EUR$/m', $texto);
        $this->assertMatchesRegularExpression('/^MB WAY +6,00 EUR$/m', $texto);
        $this->assertMatchesRegularExpression('/^Contado +70,00 EUR$/m', $texto);
        $this->assertStringContainsString('CERTO', $texto);
        $this->assertMatchesRegularExpression('/^1 x 50,00 +50,00 EUR$/m', $texto);
        // Linhas de valores cabem numa linha da impressora (32 colunas)
        foreach (collect($fecho->payload['linhas'])->filter(fn ($l) => is_string($l) && str_ends_with($l, ' EUR')) as $l) {
            $this->assertLessThanOrEqual(32, mb_strlen($l), $l);
        }
    }

    public function test_sem_impressora_do_agente_o_talao_abre_no_browser(): void
    {
        $this->abrirCaixa($this->postos[2], 20);
        Impressora::query()->update(['tipo' => Impressora::TIPO_NAVEGADOR]);
        $caixa = CaixaDiaria::where('ponto', $this->postos[2]->localizacao)->firstOrFail();

        $this->actingAs($this->admin)->patch(route('caixa.fechar', $caixa), ['valor_contado' => 18])
            ->assertSessionHas('talao_caixa', route('caixa.talao', $caixa));

        $this->actingAs($this->admin)->get(route('caixa.talao', $caixa))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Caixa/Talao')->where('payload.subtitulo', 'FECHO DE CAIXA')->where('modo', 'navegador'));
    }

    public function test_fecho_no_pos_pede_pin_e_imprime_com_produtos_vendidos(): void
    {
        $this->abrirCaixa($this->postos[1], 50);
        $this->imperial->update(['gerir_stock' => true, 'stock_atual' => 10]);
        $this->vender($this->postos[1], [[$this->imperial, 3]])->assertSessionHasNoErrors(); // 4,50
        PrintJob::query()->delete();

        $sessao = $this->sessao($this->postos[1]);

        // PIN errado nao fecha
        $this->withSession($sessao)->post(route('pos.caixa.fechar'), ['valor_contado' => 54.5, 'pin' => '0000'])
            ->assertSessionHasErrors('pin');
        $this->assertSame('aberta', CaixaDiaria::where('ponto', $this->postos[1]->localizacao)->value('estado'));

        $this->withSession($sessao)->post(route('pos.caixa.fechar'), ['valor_contado' => 54, 'pin' => '1234'])
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Caixa fechada. Faltam 0,50 €.');

        $caixa = CaixaDiaria::where('ponto', $this->postos[1]->localizacao)->firstOrFail();
        $this->assertSame('fechada', $caixa->estado);
        $this->assertSame('Teste', $caixa->fechado_por_nome);
        $this->assertNull($caixa->fechado_user_id);

        $job = PrintJob::where('tipo', 'talao_caixa')->sole();
        $this->assertSame($this->postos[1]->impressora_id, $job->impressora_id);
        $linhas = collect($job->payload['linhas'])->map(fn ($l) => is_array($l) ? $l['texto'] : $l);
        $this->assertContains('3 x Imperial', $linhas);
        $this->assertContains('   stock: 7', $linhas);
        $this->assertTrue($linhas->contains(fn ($l) => str_contains($l, '(Teste)')));

        // Depois de fechada nao se vende
        $this->vender($this->postos[1], [[$this->imperial, 1]])->assertSessionHasErrors('ponto_bar');
    }

    public function test_anular_depois_de_2_minutos_precisa_do_pin_da_comissao(): void
    {
        Configuracao::updateOrCreate(['chave' => 'comissao_pin'], ['valor' => \Illuminate\Support\Facades\Hash::make('9999')]);
        $this->abrirCaixa($this->postos[1]);
        $this->vender($this->postos[1], [[$this->imperial, 1]]);
        $pedido = Pedido::latest('id')->first();
        $sessao = $this->sessao($this->postos[1]);

        // Engano logo a seguir, no mesmo posto: nao pede PIN
        $this->travel(1)->minutes();
        $this->withSession($sessao)->get(route('pos.senhas'))->assertJsonPath('senhas.0.precisa_pin', false);

        $this->travel(5)->minutes();
        $this->withSession($sessao)->post(route('pos.pedido.anular', $pedido), ['motivo' => 'Cliente desistiu'])
            ->assertSessionHasErrors('pin_comissao');
        $this->withSession($sessao)->post(route('pos.pedido.anular', $pedido), ['motivo' => 'Cliente desistiu', 'pin_comissao' => '1111'])
            ->assertSessionHasErrors('pin_comissao');
        $this->assertNotSame('cancelado', $pedido->fresh()->estado);

        $this->withSession($sessao)->post(route('pos.pedido.anular', $pedido), ['motivo' => 'Cliente desistiu', 'pin_comissao' => '9999'])
            ->assertSessionHasNoErrors();
        $this->assertSame('cancelado', $pedido->fresh()->estado);
        $this->assertStringContainsString('autorizado pela comissao', $pedido->fresh()->motivo_anulacao);
    }

    public function test_relatorio_mostra_vendas_por_operador_e_stock(): void
    {
        $this->abrirCaixa($this->postos[1]);
        $this->imperial->update(['gerir_stock' => true, 'stock_atual' => 20]);
        $this->vender($this->postos[1], [[$this->imperial, 2]]);
        $this->vender($this->postos[1], [[$this->imperial, 3]], metodo: 'mbway');
        $hoje = now()->subHours(12)->toDateString();

        $this->actingAs($this->admin)->get(route('relatorios.periodo', ['data_inicio' => $hoje, 'data_fim' => $hoje]))
            ->assertInertia(fn ($page) => $page
                ->where('vendas_por_operador.0.operador', 'Teste')
                ->where('vendas_por_operador.0.pedidos', 2)
                ->where('vendas_por_operador.0.dinheiro', 3)
                ->where('vendas_por_operador.0.mbway', 4.5)
                ->where('stock', fn ($stock) => collect($stock)->contains(fn ($l) => $l['nome'] === 'Imperial' && $l['inicial'] == 20 && $l['vendido'] == 5 && $l['final'] == 15)));

        $res = $this->actingAs($this->admin)->get(route('relatorios.pdf', ['data_inicio' => $hoje, 'data_fim' => $hoje, 'formato' => 'csv', 'tabela' => 'stock']));
        $this->assertStringContainsString('Imperial;Bebidas;20;0;5;15;15', $res->streamedContent());
    }

    public function test_pos_do_restaurante_fecha_a_caixa_do_restaurante(): void
    {
        $impressora = Impressora::create(['nome' => 'Sala', 'secao' => 'contas', 'tipo' => Impressora::TIPO_REDE, 'host' => '10.0.0.9', 'porta' => 9100, 'ativa' => true]);
        $rest = PosSession::create(['nome' => 'Sala', 'pin' => '4321', 'localizacao' => 'Sala', 'tipo' => 'restaurante', 'impressora_id' => $impressora->id, 'ativo' => true]);
        $this->actingAs($this->admin)->post(route('caixa.store'), ['ponto' => 'Restaurante', 'fundo_maneio' => 30]);
        auth()->logout();
        $sessao = ['pos_id' => $rest->id, 'pos_nome' => 'Sala', 'pos_tipo' => 'restaurante', 'pos_localizacao' => 'Sala', 'pos_operador' => 'Rui'];

        $this->withSession($sessao)->get(route('pos.rest.mesas'))->assertInertia(fn ($page) => $page->where('caixa.ponto', 'Restaurante'));
        $this->withSession($sessao)->post(route('pos.rest.caixa.fechar'), ['valor_contado' => 30, 'pin' => '4321'])
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Caixa fechada. Bate certo.');

        $this->assertSame('fechada', CaixaDiaria::where('ponto', 'Restaurante')->value('estado'));
        $this->assertSame($impressora->id, PrintJob::where('tipo', 'talao_caixa')->sole()->impressora_id);
    }

    private function abrirCaixa(PosSession $posto, float $fundo = 50): void
    {
        $this->actingAs($this->admin)
            ->post(route('caixa.store'), ['ponto' => $posto->localizacao, 'fundo_maneio' => $fundo])
            ->assertSessionHasNoErrors();
    }

    private function abrirCaixas(float $fundo = 50): void
    {
        foreach ($this->postos as $posto) {
            $this->abrirCaixa($posto, $fundo);
        }
    }

    private function sessao(PosSession $posto): array
    {
        return [
            'pos_id' => $posto->id,
            'pos_nome' => $posto->nome,
            'pos_tipo' => $posto->tipo,
            'pos_localizacao' => $posto->localizacao,
            'pos_operador' => 'Teste',
        ];
    }

    /**
     * @param  array<int, array{0: Produto, 1: int, 2?: int}>  $itens  [produto, quantidade, ja_tem]
     */
    private function vender(PosSession $posto, array $itens, ?float $recebido = null, ?float $troco = null, array $juntar = [], array $devolvidos = [], string $metodo = 'dinheiro')
    {
        $total = collect($itens)->sum(fn ($i) => (float) $i[0]->preco * $i[1]
            + (float) $i[0]->caucao * max(0, $i[1] - ($i[2] ?? 0)));

        auth()->logout();

        return $this->withSession($this->sessao($posto))->post(route('pos.prepago.store'), [
            'valor_recebido' => $recebido ?? $total,
            'troco' => $troco ?? max(0, ($recebido ?? $total) - $total),
            'items' => collect($itens)->map(fn ($i) => [
                'produto_id' => $i[0]->id,
                'quantidade' => $i[1],
                'ja_tem' => $i[2] ?? 0,
            ])->all(),
            'devolvidos' => collect($devolvidos)->map(fn ($d) => ['produto_id' => $d[0]->id, 'quantidade' => $d[1]])->all(),
            'juntar' => $juntar,
            'metodo_pagamento' => $metodo,
        ]);
    }

    private function texto(PrintJob $job): string
    {
        return collect($job->payload['linhas'] ?? [])
            ->map(fn ($l) => is_array($l) ? ($l['texto'] ?? '') : $l)
            ->implode("\n");
    }
}
