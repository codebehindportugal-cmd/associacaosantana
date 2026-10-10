<?php

namespace Tests\Feature\Pos;

use App\Models\CaixaDiaria;
use App\Models\CaucaoDevolucao;
use App\Models\Categoria;
use App\Models\Impressora;
use App\Models\Pedido;
use App\Models\PosSession;
use App\Models\Produto;
use App\Models\TalaoConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Os taloes do POS sem internet sao montados no browser (resources/js/pos/taloes.js).
 * Este teste faz vendas reais pelo POS online com impressora USB, apanha os
 * taloes que o servidor manda imprimir e confirma que o browser monta
 * exatamente os mesmos para a mesma venda.
 */
class TaloesParidadeTest extends TestCase
{
    use RefreshDatabase;

    private PosSession $posto;

    private array $p = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->node()) {
            $this->markTestSkipped('Node nao esta instalado: nao da para comparar os taloes do browser.');
        }

        Carbon::setTestNow(Carbon::parse('2026-10-10 21:37:00'));
        TalaoConfig::create([
            'nome' => 'Festa', 'titulo' => 'Carvalhal Fest', 'cabecalho' => "2.ª Edição\nCarvalhal Benfeito",
            'rodape' => "Obrigado!\nNao serve de fatura", 'instrucoes_individual' => 'Levante na tasquinha',
            'prepago_apenas_individuais' => true, 'ativo' => true, 'em_uso' => true,
        ]);
        TalaoConfig::esquecer();

        $usb = Impressora::create(['nome' => 'USB', 'secao' => 'bar', 'tipo' => Impressora::TIPO_WEBUSB, 'ativa' => true]);
        $this->posto = PosSession::create(['nome' => 'Bar 1', 'pin' => '1234', 'localizacao' => 'Tenda', 'tipo' => 'bar', 'ativo' => true, 'impressora_id' => $usb->id]);
        CaixaDiaria::create(['data' => today(), 'ponto' => 'Tenda', 'fundo_maneio' => 50, 'estado' => 'aberta']);

        $cat = fn ($nome, $secao) => tap(Categoria::firstOrCreate(['nome' => $nome], ['secao' => $secao]))->update(['secao' => $secao]);
        $bebidas = $cat('Bebidas', 'bebidas');
        $frango = $cat('Frango', 'frango');
        $acomp = $cat('Acompanhamentos', 'acompanhamentos');
        $sobremesas = $cat('Sobremesas', 'sobremesas');
        $mk = fn ($c, $nome, $preco, $caucao = 0) => Produto::create(['categoria_id' => $c->id, 'nome' => $nome, 'preco' => $preco, 'caucao' => $caucao, 'disponivel' => true, 'disponivel_bar' => true]);
        $this->p = [
            'imperial' => $mk($bebidas, 'Imperial', 1.5),
            'agua' => $mk($bebidas, 'Água', 1),
            'metro' => $mk($bebidas, 'Metro de Imperial', 12, 5),
            'frango' => $mk($frango, 'Frango', 8),
            'batata' => $mk($acomp, 'Batata Frita', 2.5),
            'baba' => $mk($sobremesas, 'Baba de Camelo', 2.5),
        ];
    }

    private function node(): ?string
    {
        $caminho = trim((string) shell_exec('command -v node 2>/dev/null'));

        return $caminho !== '' ? $caminho : null;
    }

    private function sessao(): array
    {
        return ['pos_id' => $this->posto->id, 'pos_nome' => 'Bar 1', 'pos_tipo' => 'bar', 'pos_localizacao' => 'Tenda', 'pos_operador' => 'Ana Sá'];
    }

    /** Venda pelo POS online; devolve os taloes que o servidor mandou imprimir. */
    private function vender(array $itens, array $extra = []): array
    {
        $res = $this->withSession($this->sessao())->post(route('pos.prepago.store'), array_merge([
            'items' => collect($itens)->map(fn ($i) => ['produto_id' => $this->p[$i[0]]->id, 'quantidade' => $i[1], 'ja_tem' => $i[2] ?? 0])->all(),
        ], $extra));
        $res->assertSessionHasNoErrors();

        return session('imprimir')['escpos'];
    }

    /** A mesma venda como o browser a guarda. */
    private function vendaLocal(Pedido $pedido): array
    {
        $pedido->load('items.produto');

        return [
            'codigo' => $pedido->codigo_senha,
            'ponto' => $pedido->ponto_bar,
            'operador' => $pedido->operador_nome,
            'items' => $pedido->items->map(fn ($i) => [
                'nome' => $i->produto->nome, 'quantidade' => (int) $i->quantidade, 'preco' => (float) $i->preco_unitario,
                'secao' => $i->secao, 'talao_individual' => (bool) $i->produto->talao_individual,
            ])->all(),
            'total' => (float) $pedido->total,
            'caucao_cobrada' => (float) $pedido->caucao_cobrada,
            'caucao_descontada' => (float) $pedido->caucao_descontada,
            'valor_recebido' => (float) $pedido->valor_recebido,
            'troco' => (float) $pedido->troco,
            'doacao' => (float) $pedido->doacao,
            'metodo' => $pedido->metodo_pagamento,
            'juntar' => $pedido->juntar ?? [],
        ];
    }

    private function config(): array
    {
        $dados = $this->withSession($this->sessao())->getJson(route('pos.offline.dados'))->json();

        return $dados['talao'] + ['metodos' => $dados['metodos']];
    }

    public function test_taloes_do_browser_sao_iguais_aos_do_servidor(): void
    {
        $casos = [];
        $caso = function (string $nome, array $esperado, array $extra = []) use (&$casos) {
            $casos[] = ['nome' => $nome, 'venda' => $this->vendaLocal(Pedido::latest('id')->first()), 'config' => $this->config(), 'hora' => '21:37', 'esperado' => $esperado, 'ignorar_gaveta' => true] + $extra;
        };

        $caso('uma senha por unidade', $this->vender([['imperial', 2], ['frango', 1], ['agua', 1]], ['valor_recebido' => 20, 'troco' => 7]));
        $caso('comida e bebidas juntas', $this->vender([['frango', 2], ['batata', 1], ['imperial', 3], ['baba', 1]], ['valor_recebido' => 30, 'troco' => 0.5, 'juntar' => ['cozinha' => 1, 'bebidas' => 1]]));
        $caso('mb way', $this->vender([['agua', 1]], ['valor_recebido' => 0, 'metodo_pagamento' => 'mbway']));
        $caso('caucao cobrada e quem ja tem', $this->vender([['metro', 2, 1]], ['valor_recebido' => 50, 'troco' => 21]));
        $caso('caucao devolvida maior que a senha', $this->vender([['imperial', 1]], ['valor_recebido' => 0, 'troco' => 3.5, 'devolvidos' => [['produto_id' => $this->p['metro']->id, 'quantidade' => 1]]]));
        $caso('doacao do troco', $this->vender([['imperial', 1]], ['valor_recebido' => 5, 'troco' => 0]));

        // Sem senhas por seccao: so os produtos com talao individual
        TalaoConfig::query()->update(['prepago_apenas_individuais' => false]);
        TalaoConfig::esquecer();
        $this->p['frango']->update(['talao_individual' => true]);
        $caso('so talao individual', $this->vender([['frango', 2], ['imperial', 1]], ['valor_recebido' => 20, 'troco' => 2.5]));
        TalaoConfig::query()->update(['prepago_apenas_individuais' => true]);
        TalaoConfig::esquecer();

        // 2a via e anulacao
        $pedido = Pedido::where('metodo_pagamento', 'mbway')->first();
        $this->withSession($this->sessao())->post(route('pos.pedido.reimprimir', $pedido), ['o' => 'tudo']);
        $esperado = session('imprimir')['escpos'];
        $casos[] = ['nome' => '2a via', 'tipo' => 'segunda_via', 'venda' => $this->vendaLocal($pedido), 'config' => $this->config(), 'hora' => '21:37', 'esperado' => $esperado, 'ignorar_gaveta' => true];

        $this->withSession($this->sessao())->post(route('pos.pedido.anular', $pedido), ['motivo' => 'Cliente desistiu']);
        $esperado = session('imprimir')['escpos'];
        $pedido->refresh();
        $casos[] = ['nome' => 'anulacao', 'tipo' => 'anulacao', 'venda' => $this->vendaLocal($pedido), 'config' => $this->config(), 'devolver' => (float) $pedido->valor_devolvido,
            'anulacao' => ['vendidaAs' => '21:37', 'anuladaAs' => '21:37', 'por' => $pedido->anulado_por, 'motivo' => $pedido->motivo_anulacao],
            'esperado' => $esperado, 'ignorar_gaveta' => true];

        // Devolucao de caucao em dinheiro
        $this->withSession($this->sessao())->post(route('pos.caucao.devolver'), ['produto_id' => $this->p['metro']->id, 'quantidade' => 2]);
        $esperado = session('imprimir')['escpos'] ?? [];
        $dev = CaucaoDevolucao::where('modo', 'dinheiro')->latest('id')->first();
        $casos[] = ['nome' => 'devolucao de caucao', 'tipo' => 'caucao', 'config' => $this->config(), 'venda' => null,
            'caucao' => ['ponto' => 'Tenda', 'operador' => 'Ana Sá', 'hora' => '21:37', 'quantidade' => 2, 'nome' => 'Metro de Imperial', 'valorTotal' => (float) $dev->valor_total],
            'esperado' => $esperado, 'ignorar_gaveta' => false];

        $this->assertNotEmpty(end($casos)['esperado'], 'A devolucao de caucao nao mandou talao para a USB');

        $processo = proc_open([$this->node(), base_path('tests/js/taloes-paridade.mjs')], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        fwrite($pipes[0], json_encode($casos));
        fclose($pipes[0]);
        $saida = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        proc_close($processo);

        $this->assertSame('OK', trim($saida), $saida);
    }
}
