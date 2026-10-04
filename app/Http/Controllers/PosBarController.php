<?php

namespace App\Http\Controllers;

use App\Models\CaixaDiaria;
use App\Models\CaucaoDevolucao;
use App\Models\Configuracao;
use App\Models\Pedido;
use App\Models\PosSession;
use App\Models\Produto;
use App\Models\TalaoConfig;
use App\Services\PrintJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PosBarController extends Controller
{
    public function index(): Response
    {
        $ponto = session('pos_localizacao') ?: session('pos_nome');

        return Inertia::render('Pos/Index', [
            'posNome' => session('pos_nome'),
            'pontoBar' => $ponto,
            'caixaAberta' => $this->caixaAberta($ponto),
            'produtos' => Produto::with('categoria')
                ->disponiveisBar()
                ->orderBy('nome')
                ->get(),
            'senhasHoje' => Pedido::where('ponto_bar', $ponto)
                ->where('tipo', 'bar_prepago')
                ->whereRaw('DATE(DATE_SUB(created_at, INTERVAL 12 HOUR)) = ?', [now()->subHours(12)->toDateString()])
                ->with('items.produto')
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }

    public function storePrepago(Request $request, PrintJobService $printJobs): RedirectResponse
    {
        $data = $request->validate([
            'valor_recebido' => ['required', 'numeric', 'min:0'],
            'troco' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produto_id' => ['required', $this->produtoBarRule()],
            'items.*.quantidade' => ['required', 'integer', 'min:1'],
            // Quantos destes o cliente ja tem (metro/jarro de outra vez): nao se cobra caucao
            'items.*.ja_tem' => ['nullable', 'integer', 'min:0'],
            // Metros devolvidos que o cliente troca por bebidas nesta senha
            'devolvidos' => ['nullable', 'array'],
            'devolvidos.*.produto_id' => ['required', Rule::exists('produtos', 'id')->where(fn ($q) => $q->where('caucao', '>', 0))],
            'devolvidos.*.quantidade' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $ponto = session('pos_localizacao') ?: session('pos_nome');

        if (! $this->caixaAberta($ponto)) {
            return back()->withErrors(['ponto_bar' => 'Abre a caixa deste ponto no backoffice antes de vender.']);
        }

        return DB::transaction(function () use ($data, $ponto, $printJobs) {
            $produtos = Produto::with('categoria')->whereIn('id', collect($data['items'])->pluck('produto_id'))->get()->keyBy('id');
            $total = round(collect($data['items'])->sum(fn ($item) => (float) $produtos[$item['produto_id']]->preco * (int) $item['quantidade']), 2);

            // Caucao: cobrada a parte (nao e receita) e, se o cliente trouxe
            // metros, descontada aqui em vez de lhe dar o dinheiro.
            // So se cobra caucao pelos artigos que o cliente ainda nao tem
            // (quem traz o metro/jarro de outra vez nao paga outra caucao).
            $caucaoCobrada = round(collect($data['items'])->sum(fn ($item) => (float) $produtos[$item['produto_id']]->caucao
                * max(0, (int) $item['quantidade'] - min((int) ($item['ja_tem'] ?? 0), (int) $item['quantidade']))), 2);
            $devolvidos = collect($data['devolvidos'] ?? []);
            $produtosCaucao = Produto::whereIn('id', $devolvidos->pluck('produto_id'))->get()->keyBy('id');
            $caucaoDescontada = round($devolvidos->sum(fn ($d) => (float) $produtosCaucao[$d['produto_id']]->caucao * (int) $d['quantidade']), 2);

            if ($caucaoDescontada > $total + $caucaoCobrada) {
                return back()->withErrors(['devolvidos' => 'O saldo das caucoes devolvidas e maior do que a senha. Junta mais bebidas ou devolve o resto em dinheiro.']);
            }

            $aPagar = round($total + $caucaoCobrada - $caucaoDescontada, 2);
            $valorRecebido = round((float) $data['valor_recebido'], 2);
            $troco = round((float) ($data['troco'] ?? 0), 2);
            $excedente = round($valorRecebido - $aPagar, 2);

            if ($valorRecebido < $aPagar) {
                return back()->withErrors(['valor_recebido' => 'O valor recebido nao pode ser inferior ao total.']);
            }

            if ($troco > $excedente) {
                return back()->withErrors(['troco' => 'O troco nao pode ser superior ao valor a devolver.']);
            }

            $pedido = Pedido::create([
                'pos_id' => session('pos_id'),
                'operador_nome' => session('pos_operador') ?: session('pos_nome'),
                'tipo' => 'bar_prepago',
                'estado' => 'pronto',
                'numero_senha' => $this->proximaSenhaBar(),
                'pago_antecipado' => true,
                'ponto_bar' => $ponto,
                'total' => $total,
                'caucao_cobrada' => $caucaoCobrada,
                'caucao_descontada' => $caucaoDescontada,
                'valor_recebido' => $valorRecebido,
                'troco' => $troco,
                'doacao' => max(0, round($excedente - $troco, 2)),
                'metodo_pagamento' => 'dinheiro',
            ]);

            foreach ($data['items'] as $item) {
                $produto = $produtos[$item['produto_id']];
                $pedido->items()->create([
                    'produto_id' => $produto->id,
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $produto->preco,
                    'secao' => $produto->categoria->secao,
                ]);
            }

            foreach ($devolvidos as $devolvido) {
                $produtoCaucao = $produtosCaucao[$devolvido['produto_id']];
                CaucaoDevolucao::create([
                    'produto_id' => $produtoCaucao->id,
                    'pedido_id' => $pedido->id,
                    'pos_id' => session('pos_id'),
                    'operador_nome' => session('pos_operador') ?: session('pos_nome'),
                    'ponto' => $ponto,
                    'modo' => 'bebidas',
                    'quantidade' => (int) $devolvido['quantidade'],
                    'valor_unitario' => $produtoCaucao->caucao,
                    'valor_total' => round((float) $produtoCaucao->caucao * (int) $devolvido['quantidade'], 2),
                ]);
            }

            $pedidoFull = $pedido->fresh('items.produto.categoria', 'pos');
            $secaoImp   = $this->secaoImpressora();

            $terminal = PosSession::find(session('pos_id'));
            $impressora = $terminal?->impressora;

            // Como imprime este posto e uma definicao da impressora dele:
            // rede/usb passam pelo agente, webusb e navegador imprimem na
            // propria pagina do talao. Sem impressora definida, mantem-se o
            // comportamento antigo por seccao, pelo agente.
            $viaAgente = $impressora ? $impressora->usaAgente() : true;

            // Sem isto, o talao sairia na primeira impressora da seccao
            $printJobs->paraImpressora($terminal?->impressora_id);

            // Evento com pre-pagamento: sai um talao por UNIDADE, cada um
            // cortado, agrupados por seccao para saírem pela ordem das
            // tasquinhas. A conta sai no fim.
            $porSeccao = TalaoConfig::atual()->taloesPorSeccao();
            $jobs = [];

            if ($viaAgente && $porSeccao) {
                $grupos = $printJobs->unidadesPorSeccao($pedidoFull);
                $totalTaloes = array_sum(array_map('count', $grupos));
                $numero = 0;

                foreach ($grupos as $secao => $unidades) {
                    foreach ($unidades as $nome) {
                        $numero++;
                        $jobs[] = $printJobs->criarTalaoBarUnitario($pedidoFull, $nome, $secaoImp, $numero, $totalTaloes, $secao);
                    }
                }

                // A conta sai no fim, para quem esta na caixa conferir
                $jobs[] = $printJobs->criarTalaoBar($pedidoFull, $secaoImp, 'CONTA');
            } elseif ($viaAgente) {
                $itensIndividuais = $pedidoFull->items->filter(fn ($i) => (bool) ($i->produto->talao_individual ?? false));
                $itensOutros = $pedidoFull->items->filter(fn ($i) => ! (bool) ($i->produto->talao_individual ?? false));

                $totalTaloes = (int) $itensIndividuais->sum('quantidade');
                $numero = 0;

                foreach ($itensIndividuais as $item) {
                    for ($u = 0; $u < $item->quantidade; $u++) {
                        $numero++;
                        $jobs[] = $printJobs->criarTalaoBarUnitario(
                            $pedidoFull,
                            $item->produto->nome,
                            $secaoImp,
                            $numero,
                            $totalTaloes,
                            $item->produto->categoria->secao ?? null,
                        );
                    }
                }

                if ($itensOutros->isNotEmpty()) {
                    $jobs[] = $printJobs->criarTalaoBar($pedidoFull->setRelation('items', $itensOutros), $secaoImp);
                }
            }

            // Venda a dinheiro: a gaveta abre com o primeiro talao que sai
            // (ESC p no inicio dos bytes), para nao ficar a espera do resto.
            if ($primeiro = collect($jobs)->filter()->first()) {
                $primeiro->update(['payload' => [...$primeiro->payload, 'abrir_caixa' => true]]);
            }

            $sucesso = 'Senha #'.$pedido->numero_senha.' enviada para a impressora.';

            // Pelo agente, o talao sai sozinho na impressora.
            if ($viaAgente) {
                return to_route('pos.index')->with('success', $sucesso);
            }

            // WebUSB/navegador: fica-se no ecra de venda e e o proprio POS
            // que imprime (e abre a gaveta), sem passar pela pagina do talao.
            $modo = $impressora->tipo;
            $escpos = [];

            if ($modo === 'webusb') {
                [, $escpos] = $this->taloesDoPedido($pedidoFull, $printJobs, $modo);

                if ($escpos !== []) {
                    $escpos[0]['abrir_caixa'] = true;
                }
            }

            return to_route('pos.index')
                ->with('success', $sucesso)
                ->with('imprimir', [
                    'pedido_id' => $pedido->id,
                    'modo' => $modo,
                    'url' => route('pos.pedido.talao', $pedido),
                    'escpos' => $escpos,
                ]);
        });
    }

    /**
     * Metro devolvido e o cliente quer o dinheiro: regista a saida da caucao,
     * imprime um talao de devolucao e abre a gaveta.
     */
    public function devolverCaucao(Request $request, PrintJobService $printJobs): RedirectResponse
    {
        $data = $request->validate([
            'produto_id' => ['required', Rule::exists('produtos', 'id')->where(fn ($q) => $q->where('caucao', '>', 0))],
            'quantidade' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $ponto = session('pos_localizacao') ?: session('pos_nome');

        if (! $this->caixaAberta($ponto)) {
            return back()->withErrors(['ponto_bar' => 'Abre a caixa deste ponto no backoffice antes de devolver cauções.']);
        }

        $produto = Produto::findOrFail($data['produto_id']);
        $quantidade = (int) $data['quantidade'];
        $valor = round((float) $produto->caucao * $quantidade, 2);

        $devolucao = CaucaoDevolucao::create([
            'produto_id' => $produto->id,
            'pos_id' => session('pos_id'),
            'operador_nome' => session('pos_operador') ?: session('pos_nome'),
            'ponto' => $ponto,
            'modo' => 'dinheiro',
            'quantidade' => $quantidade,
            'valor_unitario' => $produto->caucao,
            'valor_total' => $valor,
        ]);

        $terminal = PosSession::find(session('pos_id'));
        $impressora = $terminal?->impressora;
        $viaAgente = $impressora ? $impressora->usaAgente() : true;
        $mensagem = 'Caução devolvida: '.number_format($valor, 2, ',', ' ').' € ('.$quantidade.'x '.$produto->nome.').';

        if ($viaAgente) {
            $printJobs->paraImpressora($terminal?->impressora_id);
            $printJobs->criarTalaoCaucao($devolucao, $this->secaoImpressora());

            return to_route('pos.index')->with('success', $mensagem);
        }

        // WebUSB: o proprio POS imprime e abre a gaveta. No modo navegador
        // nao ha talao; a operacao fica registada na mesma.
        if ($impressora->tipo === 'webusb') {
            return to_route('pos.index')
                ->with('success', $mensagem)
                ->with('imprimir', [
                    'pedido_id' => 'caucao-'.$devolucao->id,
                    'modo' => 'webusb',
                    'escpos' => [$printJobs->payloadTalaoCaucao($devolucao)],
                ]);
        }

        return to_route('pos.index')->with('success', $mensagem);
    }

    public function talao(Pedido $pedido, PrintJobService $printJobs): Response
    {
        abort_unless($pedido->tipo === 'bar_prepago' && (int) $pedido->pos_id === (int) session('pos_id'), 404);

        $pedido->load('items.produto.categoria', 'pos');

        $talao = TalaoConfig::atual();
        $terminal = PosSession::find(session('pos_id'));
        $impressora = $terminal?->impressora;
        $modo = $impressora?->tipo ?? 'agente';

        [$taloesCliente, $taloesEscpos] = $this->taloesDoPedido($pedido, $printJobs, $modo);

        return Inertia::render('Pos/TalaoSenha', [
            'pedido' => $pedido,
            'talao' => [
                'titulo' => $talao->tituloImpresso(),
                'cabecalho' => array_column($talao->linhasCabecalho(), 'texto'),
                'rodape' => $talao->linhasRodape(),
                'instrucoes' => array_column($talao->linhasInstrucoes(), 'texto'),
            ],
            'taloesCliente' => $taloesCliente,
            'modoImpressao' => $modo,
            'taloesEscpos' => $taloesEscpos,
        ]);
    }

    /**
     * Taloes de um pedido pre-pago: os do cliente (um por unidade) para o
     * HTML e, em webusb/navegador, os mesmos em ESC/POS com a conta no fim.
     *
     * @return array{0: array, 1: array}
     */
    private function taloesDoPedido(Pedido $pedido, PrintJobService $printJobs, string $modo): array
    {
        $pedido->loadMissing('items.produto.categoria', 'pos');
        $porSeccao = TalaoConfig::atual()->taloesPorSeccao();

        // O cliente leva um talao por unidade. No pre-pagamento leva tudo;
        // fora dele, so os produtos marcados como talao individual.
        $grupos = $porSeccao
            ? $printJobs->unidadesPorSeccao($pedido)
            : $pedido->items
                ->filter(fn ($i) => (bool) ($i->produto->talao_individual ?? false))
                ->reduce(function (array $acc, $item) {
                    $secao = $item->secao ?: ($item->produto->categoria->secao ?? 'outros');

                    for ($u = 0; $u < (int) $item->quantidade; $u++) {
                        $acc[$secao][] = $item->produto?->nome ?? 'Produto';
                    }

                    return $acc;
                }, []);

        $taloesCliente = [];
        $taloesEscpos = [];
        $numero = 0;
        $totalTaloes = array_sum(array_map('count', $grupos));

        foreach ($grupos as $secao => $unidades) {
            foreach ($unidades as $nome) {
                $numero++;

                $taloesCliente[] = [
                    'produto' => $nome,
                    'secao' => $printJobs->nomeSecao($secao),
                    'indice' => $numero,
                    'total' => $totalTaloes,
                ];

                if (in_array($modo, ['webusb', 'navegador'], true)) {
                    $taloesEscpos[] = $printJobs->payloadTalaoUnitario($pedido, $nome, $numero, $totalTaloes, $secao);
                }
            }
        }

        if ($taloesEscpos !== []) {
            $taloesEscpos[] = $printJobs->payloadTalaoBar($pedido, 'CONTA');
        }

        return [$taloesCliente, $taloesEscpos];
    }

    private function caixaAberta(string $ponto): bool
    {
        return CaixaDiaria::abertaParaPonto($ponto) !== null;
    }

    private function proximaSenhaBar(): int
    {
        $config = Configuracao::where('chave', 'ultima_senha_bar')->lockForUpdate()->firstOrCreate(
            ['chave' => 'ultima_senha_bar'],
            ['valor' => '0', 'descricao' => 'Ultima senha diaria emitida no bar']
        );

        $senha = ((int) $config->valor) + 1;
        $config->update(['valor' => (string) $senha]);

        return $senha;
    }

    private function secaoImpressora(): string
    {
        // O tipo do terminal manda; a localizacao so serve de reforco.
        if (session('pos_tipo') === 'cafe') {
            return 'cafe';
        }

        if (session('pos_tipo') === 'bar') {
            return 'bar';
        }

        $ponto = session('pos_localizacao') ?: session('pos_nome') ?: '';

        return preg_match('/caf[eé]/iu', $ponto) ? 'cafe' : 'bar';
    }

    private function produtoBarRule()
    {
        return Rule::exists('produtos', 'id')
            ->where('disponivel', true)
            ->where('disponivel_bar', true);
    }
}
