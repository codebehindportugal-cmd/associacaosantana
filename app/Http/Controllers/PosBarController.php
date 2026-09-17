<?php

namespace App\Http\Controllers;

use App\Models\CaixaDiaria;
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
        ]);

        $ponto = session('pos_localizacao') ?: session('pos_nome');

        if (! $this->caixaAberta($ponto)) {
            return back()->withErrors(['ponto_bar' => 'Abre a caixa deste ponto no backoffice antes de vender.']);
        }

        return DB::transaction(function () use ($data, $ponto, $printJobs) {
            $produtos = Produto::with('categoria')->whereIn('id', collect($data['items'])->pluck('produto_id'))->get()->keyBy('id');
            $total = round(collect($data['items'])->sum(fn ($item) => (float) $produtos[$item['produto_id']]->preco * (int) $item['quantidade']), 2);
            $valorRecebido = round((float) $data['valor_recebido'], 2);
            $troco = round((float) ($data['troco'] ?? 0), 2);
            $excedente = round($valorRecebido - $total, 2);

            if ($valorRecebido < $total) {
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

            if ($viaAgente && $porSeccao) {
                $grupos = $printJobs->unidadesPorSeccao($pedidoFull);
                $totalTaloes = array_sum(array_map('count', $grupos));
                $numero = 0;

                foreach ($grupos as $secao => $unidades) {
                    foreach ($unidades as $nome) {
                        $numero++;
                        $printJobs->criarTalaoBarUnitario($pedidoFull, $nome, $secaoImp, $numero, $totalTaloes, $secao);
                    }
                }

                // A conta sai no fim, para quem esta na caixa conferir
                $printJobs->criarTalaoBar($pedidoFull, $secaoImp, 'CONTA');
            } elseif ($viaAgente) {
                $itensIndividuais = $pedidoFull->items->filter(fn ($i) => (bool) ($i->produto->talao_individual ?? false));
                $itensOutros = $pedidoFull->items->filter(fn ($i) => ! (bool) ($i->produto->talao_individual ?? false));

                $totalTaloes = (int) $itensIndividuais->sum('quantidade');
                $numero = 0;

                foreach ($itensIndividuais as $item) {
                    for ($u = 0; $u < $item->quantidade; $u++) {
                        $numero++;
                        $printJobs->criarTalaoBarUnitario(
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
                    $printJobs->criarTalaoBar($pedidoFull->setRelation('items', $itensOutros), $secaoImp);
                }
            }

            return to_route('pos.pedido.talao', $pedido);
        });
    }

    public function talao(Pedido $pedido, PrintJobService $printJobs): Response
    {
        abort_unless($pedido->tipo === 'bar_prepago' && (int) $pedido->pos_id === (int) session('pos_id'), 404);

        $pedido->load('items.produto.categoria', 'pos');

        $talao = TalaoConfig::atual();
        $porSeccao = $talao->taloesPorSeccao();
        $terminal = PosSession::find(session('pos_id'));
        $impressora = $terminal?->impressora;
        $modo = $impressora?->tipo ?? 'agente';

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
