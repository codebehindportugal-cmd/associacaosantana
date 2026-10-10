<?php

namespace App\Http\Controllers;

use App\Models\CaixaDiaria;
use App\Models\CaucaoDevolucao;
use App\Models\Configuracao;
use App\Models\Pedido;
use App\Models\PosSession;
use App\Models\PrintJob;
use App\Models\Produto;
use App\Models\TalaoConfig;
use App\Services\CaixaService;
use App\Services\PrintJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            // Pre-pagamento: o operador pode juntar as senhas de uma seccao se o cliente pedir
            'juntarFolhas' => TalaoConfig::atual()->taloesPorSeccao(),
            // O que este posto junta por omissao (configurado no proprio POS)
            'juntarPadrao' => $this->juntarPadraoDoPosto(),
            // Resumo da caixa deste ponto, para o fecho no proprio POS
            'caixa' => fn () => PosCaixaController::resumoDoPosto(),
            // Posto que trabalha sem internet (vende localmente e envia depois)
            'offline' => (bool) PosSession::find(session('pos_id'))?->offline,
            'posId' => (int) session('pos_id'),
            'operador' => session('pos_operador') ?: session('pos_nome'),
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
            // Senhas juntas por seccao: que grupos saem numa folha (o resto, uma a uma)
            'juntar' => ['nullable', 'array'],
            'juntar.*' => ['boolean'],
            'metodo_pagamento' => ['nullable', Rule::in(array_keys(Pedido::METODOS_PREPAGO))],
        ]);

        $metodo = $data['metodo_pagamento'] ?? 'dinheiro';

        $juntar = array_map('boolval', array_intersect_key($data['juntar'] ?? [], PrintJobService::GRUPOS_JUNTOS));
        $ponto = session('pos_localizacao') ?: session('pos_nome');

        if (! $this->caixaAberta($ponto)) {
            return back()->withErrors(['ponto_bar' => 'Abre a caixa deste ponto no backoffice antes de vender.']);
        }

        return DB::transaction(function () use ($data, $ponto, $printJobs, $juntar, $metodo) {
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

            $aPagar = round($total + $caucaoCobrada - $caucaoDescontada, 2);

            // Saldo das caucoes maior do que a senha: o resto devolve-se em
            // dinheiro da gaveta (fica como troco; o cliente pode doa-lo).
            if ($aPagar < 0) {
                $metodo = 'dinheiro';
            }
            // MB WAY e contactless: paga-se o valor certo, sem troco nem gaveta
            $valorRecebido = $metodo === 'dinheiro' ? round((float) $data['valor_recebido'], 2) : max(0, $aPagar);
            $troco = $metodo === 'dinheiro' ? round((float) ($data['troco'] ?? 0), 2) : 0.0;
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
                'metodo_pagamento' => $metodo,
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

            $pedido->update(['juntar' => $juntar ?: null]);

            return $this->imprimirPedido(
                $pedido,
                $printJobs,
                abrirGaveta: $metodo === 'dinheiro',
                sucesso: 'Senha #'.$pedido->codigo_senha.' enviada para a impressora.',
            );
        });
    }

    /**
     * Senhas anteriores deste ponto (o dia do evento vai das 12h as 12h), para
     * reimprimir ou anular a pedido do cliente.
     */
    public function senhas(Request $request): JsonResponse
    {
        $ponto = session('pos_localizacao') ?: session('pos_nome');
        $numero = (int) $request->query('numero');

        $pedidos = Pedido::where('tipo', 'bar_prepago')
            ->where('ponto_bar', $ponto)
            ->where('created_at', '>=', now()->subHours(self::HORAS_SENHAS_ANTERIORES))
            ->when($numero > 0, fn ($q) => $q->where('numero_senha', $numero))
            ->with('items.produto')
            ->latest('id')
            ->limit(80)
            ->get();

        return response()->json([
            'senhas' => $pedidos->map(fn (Pedido $p) => $this->resumoSenha($p))->values(),
        ]);
    }

    /** 2a via: tudo (senhas + conta) ou so a conta. */
    public function reimprimir(Request $request, Pedido $pedido, PrintJobService $printJobs): RedirectResponse
    {
        $this->autorizarSenha($pedido);
        abort_if($pedido->estado === 'cancelado', 422, 'Esta senha foi anulada.');

        $data = $request->validate(['o' => ['required', Rule::in(['tudo', 'conta'])]]);

        $pedido->increment('reimpressoes');

        return $this->imprimirPedido(
            $pedido->fresh(),
            $printJobs,
            abrirGaveta: false,
            sucesso: 'Senha #'.$pedido->codigo_senha.' reimpressa ('.($data['o'] === 'conta' ? 'so a conta' : 'senhas e conta').').',
            segundaVia: $data['o'],
        );
    }

    /**
     * Anula a senha a pedido do cliente: deixa de contar nas vendas e na caixa,
     * os taloes que ainda nao sairam ja nao saem, e sai um talao "ANULADA" com o
     * valor a devolver. Metros que o cliente tinha trocado nesta senha passam a
     * devolucao em dinheiro.
     */
    public function anular(Request $request, Pedido $pedido, PrintJobService $printJobs): RedirectResponse
    {
        $this->autorizarSenha($pedido);

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
            'pin_comissao' => ['nullable', 'string'],
        ], [
            'motivo.required' => 'Escreve o motivo da anulacao.',
            'motivo.min' => 'Escreve o motivo da anulacao.',
        ]);

        if ($pedido->estado === 'cancelado') {
            return back()->withErrors(['motivo' => 'Esta senha ja estava anulada.']);
        }

        // Fora do engano imediato no proprio posto, so a comissao anula
        $autorizada = false;
        if ($this->anulacaoPrecisaPin($pedido)) {
            $hash = Configuracao::where('chave', 'comissao_pin')->value('valor');
            if (empty($data['pin_comissao']) || ! Hash::check($data['pin_comissao'], $hash)) {
                return back()->withErrors(['pin_comissao' => ! empty($data['pin_comissao']) ? 'PIN da comissao errado.' : 'Esta anulacao precisa do PIN da comissao.']);
            }
            $autorizada = true;
        }
        $data['motivo'] .= $autorizada ? ' (autorizado pela comissao)' : (session('pos_comissao') ? ' (comissao: '.session('pos_comissao_nome').')' : '');

        if ($pedido->created_at->lt(now()->subHours(self::HORAS_SENHAS_ANTERIORES))) {
            return back()->withErrors(['motivo' => 'So se anulam senhas do proprio dia. Fala com a comissao.']);
        }

        return DB::transaction(function () use ($pedido, $data, $printJobs) {
            $pedido = Pedido::lockForUpdate()->findOrFail($pedido->id);

            if ($pedido->estado === 'cancelado') {
                return back()->withErrors(['motivo' => 'Esta senha ja estava anulada.']);
            }

            $pago = round((float) $pedido->total + (float) $pedido->caucao_cobrada - (float) $pedido->caucao_descontada + (float) $pedido->doacao, 2);
            $metros = round((float) $pedido->caucao_descontada, 2);

            // O cliente tinha trocado metros por bebidas nesta senha: recebe a caucao em dinheiro
            CaucaoDevolucao::where('pedido_id', $pedido->id)->where('modo', 'bebidas')->update(['modo' => 'dinheiro']);

            $pedido->update([
                'estado' => 'cancelado',
                'pago_antecipado' => false,
                'anulado_em' => now(),
                'anulado_por' => session('pos_operador') ?: session('pos_nome'),
                'motivo_anulacao' => $data['motivo'],
                'valor_devolvido' => $pago + $metros,
            ]);

            // Taloes desta senha que ainda nao sairam ja nao saem
            PrintJob::where('printable_type', Pedido::class)
                ->where('printable_id', $pedido->id)
                ->whereIn('estado', ['pendente', 'processando', 'falhado'])
                ->update(['estado' => 'falhado', 'tentativas' => 10, 'ultimo_erro' => 'Senha anulada', 'reservado_ate' => null]);

            $dinheiroDaGaveta = (($pedido->metodo_pagamento ?: 'dinheiro') === 'dinheiro' ? $pago : 0) + $metros;
            $mensagem = 'Senha #'.$pedido->codigo_senha.' anulada. Devolver '.number_format($pago + $metros, 2, ',', ' ').' €'
                .(($pedido->metodo_pagamento ?: 'dinheiro') !== 'dinheiro' ? ' ('.(Pedido::METODOS_PREPAGO[$pedido->metodo_pagamento] ?? $pedido->metodo_pagamento).($metros > 0 ? ' + '.number_format($metros, 2, ',', ' ').' € de caucao em dinheiro' : '').')' : '')
                .'.';

            $terminal = PosSession::find(session('pos_id'));
            $impressora = $terminal?->impressora;
            $payload = $printJobs->payloadAnulacao($pedido, $pago + $metros);
            if ($dinheiroDaGaveta > 0) {
                $payload['abrir_caixa'] = true;
            }

            if (! $impressora || $impressora->usaAgente()) {
                $printJobs->paraImpressora($terminal?->impressora_id)->criarPayload($pedido, $payload, $this->secaoImpressora());

                return to_route('pos.index')->with('success', $mensagem);
            }

            if ($impressora->tipo === 'webusb') {
                return to_route('pos.index')->with('success', $mensagem)->with('imprimir', [
                    'pedido_id' => 'anulada-'.$pedido->id,
                    'modo' => 'webusb',
                    'escpos' => [$payload],
                ]);
            }

            return to_route('pos.index')->with('success', $mensagem);
        });
    }

    /** Horas para tras em que as senhas aparecem no POS (e se podem anular). */
    private const HORAS_SENHAS_ANTERIORES = 14;

    /** A senha tem de ser do pre-pagamento deste ponto. */
    private function autorizarSenha(Pedido $pedido): void
    {
        $ponto = session('pos_localizacao') ?: session('pos_nome');

        abort_unless($pedido->tipo === 'bar_prepago' && $pedido->ponto_bar === $ponto, 404);
    }

    private function resumoSenha(Pedido $p): array
    {
        $pago = round((float) $p->total + (float) $p->caucao_cobrada - (float) $p->caucao_descontada, 2);

        return [
            'id' => $p->id,
            'numero' => $p->codigo_senha,
            'hora' => $p->created_at?->format('H:i'),
            'operador' => $p->operador_nome,
            'total' => (float) $p->total,
            'caucao_cobrada' => (float) $p->caucao_cobrada,
            'caucao_descontada' => (float) $p->caucao_descontada,
            'pago' => $pago,
            'doacao' => (float) $p->doacao,
            'metodo' => $p->metodo_pagamento ?: 'dinheiro',
            'metodo_nome' => Pedido::METODOS_PREPAGO[$p->metodo_pagamento ?: 'dinheiro'] ?? $p->metodo_pagamento,
            'reimpressoes' => (int) $p->reimpressoes,
            'anulada' => $p->estado === 'cancelado',
            'precisa_pin' => $p->estado !== 'cancelado' && $this->anulacaoPrecisaPin($p),
            'anulado_em' => $p->anulado_em?->format('H:i'),
            'anulado_por' => $p->anulado_por,
            'motivo_anulacao' => $p->motivo_anulacao,
            'valor_devolvido' => $p->valor_devolvido !== null ? (float) $p->valor_devolvido : null,
            'itens' => $p->items->map(fn ($i) => [
                'nome' => $i->produto?->nome ?? 'Produto',
                'quantidade' => (int) $i->quantidade,
                'valor' => round((float) $i->preco_unitario * (int) $i->quantidade, 2),
            ])->values(),
        ];
    }

    /**
     * Imprime os taloes de um pedido pre-pago na impressora do posto: senhas
     * (uma por unidade ou juntas por seccao) e a conta no fim. Na 2a via os
     * taloes levam "2a VIA" e nunca abrem a gaveta.
     *
     * @param  string|null  $segundaVia  null (venda), 'tudo' ou 'conta'
     */
    private function imprimirPedido(Pedido $pedido, PrintJobService $printJobs, bool $abrirGaveta, string $sucesso, ?string $segundaVia = null): RedirectResponse
    {
        $pedidoFull = $pedido->fresh('items.produto.categoria', 'pos');
        $juntar = array_map('boolval', (array) ($pedidoFull->juntar ?? []));
        $secaoImp = $this->secaoImpressora();

        $terminal = PosSession::find(session('pos_id'));
        $impressora = $terminal?->impressora;

        // Como imprime este posto e uma definicao da impressora dele:
        // rede/usb passam pelo agente, webusb e navegador imprimem na
        // propria pagina do talao. Sem impressora definida, mantem-se o
        // comportamento antigo por seccao, pelo agente.
        $viaAgente = $impressora ? $impressora->usaAgente() : true;

        // Sem isto, o talao sairia na primeira impressora da seccao
        $printJobs->paraImpressora($terminal?->impressora_id);

        // Evento com pre-pagamento: sai um talao por UNIDADE (ou juntos por
        // seccao, a pedido do cliente), agrupados por seccao. A conta no fim.
        $porSeccao = TalaoConfig::atual()->taloesPorSeccao();
        $jobs = [];

        if ($viaAgente && $segundaVia === 'conta') {
            $jobs[] = $printJobs->criarTalaoBar($pedidoFull, $secaoImp, 'CONTA');
        } elseif ($viaAgente && $porSeccao) {
            $jobs = $printJobs->criarTaloesPrepago($pedidoFull, $secaoImp, $juntar);

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

        $jobs = collect($jobs)->filter();

        if ($segundaVia) {
            $jobs->each(fn (PrintJob $job) => $job->update(['payload' => PrintJobService::marcarSegundaVia($job->payload)]));
        } elseif ($abrirGaveta && $primeiro = $jobs->first()) {
            // Venda a dinheiro: a gaveta abre com o primeiro talao que sai
            // (ESC p no inicio dos bytes), para nao ficar a espera do resto.
            $primeiro->update(['payload' => [...$primeiro->payload, 'abrir_caixa' => true]]);
        }

        // Pelo agente, o talao sai sozinho na impressora.
        if ($viaAgente) {
            return to_route('pos.index')->with('success', $sucesso);
        }

        // WebUSB/navegador: fica-se no ecra de venda e e o proprio POS
        // que imprime (e abre a gaveta), sem passar pela pagina do talao.
        $modo = $impressora->tipo;
        $escpos = [];

        if ($modo === 'webusb') {
            [, $escpos] = $this->taloesDoPedido($pedidoFull, $printJobs, $modo, $juntar);

            if ($segundaVia === 'conta') {
                $escpos = array_slice($escpos, -1);
            }

            if ($segundaVia) {
                $escpos = array_map([PrintJobService::class, 'marcarSegundaVia'], $escpos);
            } elseif ($escpos !== [] && $abrirGaveta) {
                $escpos[0]['abrir_caixa'] = true;
            }
        }

        return to_route('pos.index')
            ->with('success', $sucesso)
            ->with('imprimir', [
                'pedido_id' => $segundaVia ? 'via-'.$pedido->id.'-'.$pedido->reimpressoes : $pedido->id,
                'modo' => $modo,
                'url' => route('pos.pedido.talao', $juntar ? [$pedido, 'juntar' => array_map('intval', $juntar)] : $pedido),
                'escpos' => $escpos,
            ]);
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

    public function talao(Request $request, Pedido $pedido, PrintJobService $printJobs): Response
    {
        abort_unless($pedido->tipo === 'bar_prepago' && (int) $pedido->pos_id === (int) session('pos_id'), 404);

        $pedido->load('items.produto.categoria', 'pos');

        $talao = TalaoConfig::atual();
        $terminal = PosSession::find(session('pos_id'));
        $impressora = $terminal?->impressora;
        $modo = $impressora?->tipo ?? 'agente';

        [$taloesCliente, $taloesEscpos] = $this->taloesDoPedido(
            $pedido,
            $printJobs,
            $modo,
            array_map('boolval', array_intersect_key((array) $request->query('juntar', []), PrintJobService::GRUPOS_JUNTOS)),
        );

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
    private function taloesDoPedido(Pedido $pedido, PrintJobService $printJobs, string $modo, array $juntar = []): array
    {
        $pedido->loadMissing('items.produto.categoria', 'pos');
        $porSeccao = TalaoConfig::atual()->taloesPorSeccao();

        // O cliente pediu alguma seccao junta: essa numa folha, o resto uma a uma
        if ($porSeccao && array_filter($juntar)) {
            $taloes = $printJobs->taloesJuntos($pedido, $juntar);
            $total = count($taloes);
            $taloesCliente = [];
            $taloesEscpos = [];

            foreach ($taloes as $i => $talao) {
                $taloesCliente[] = isset($talao['seccoes'])
                    ? [
                        'indice' => $i + 1,
                        'total' => $total,
                        'seccoes' => collect($talao['seccoes'])->map(fn ($unidades, $secao) => [
                            'nome' => $printJobs->nomeSecao($secao),
                            'unidades' => $unidades,
                        ])->values()->all(),
                    ]
                    : [
                        'indice' => $i + 1,
                        'total' => $total,
                        'produto' => $talao['nome'],
                        'secao' => $printJobs->nomeSecao($talao['secao']),
                    ];

                if (in_array($modo, ['webusb', 'navegador'], true)) {
                    $taloesEscpos[] = $printJobs->payloadTalaoJunto($pedido, $talao, $i + 1, $total);
                }
            }

            if ($taloesEscpos !== []) {
                $taloesEscpos[] = $printJobs->payloadTalaoBar($pedido, 'CONTA');
            }

            return [$taloesCliente, $taloesEscpos];
        }

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

    /** Minutos em que o proprio posto pode anular sem a comissao (engano ao vender). */
    private const MINUTOS_ANULAR_SEM_PIN = 2;

    /**
     * Pede o PIN da comissao, exceto: posto em modo comissao, ou senha tirada
     * neste posto ha menos de 2 minutos. Se nao houver PIN definido, nao pede.
     */
    private function anulacaoPrecisaPin(Pedido $pedido): bool
    {
        if (session('pos_comissao') || ! Configuracao::where('chave', 'comissao_pin')->exists()) {
            return false;
        }

        $engano = (int) $pedido->pos_id === (int) session('pos_id')
            && $pedido->created_at->gt(now()->subMinutes(self::MINUTOS_ANULAR_SEM_PIN));

        return ! $engano;
    }

    /** Guarda, para este posto, que grupos de senhas saem juntos por omissao. */
    public function guardarJuntarPadrao(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'juntar' => ['present', 'array'],
            'juntar.*' => ['boolean'],
        ]);

        $posto = PosSession::findOrFail(session('pos_id'));
        $posto->update([
            'juntar_padrao' => array_map('boolval', array_intersect_key($data['juntar'], PrintJobService::GRUPOS_JUNTOS)),
        ]);

        return back()->with('success', 'Configuração das senhas guardada para este posto.');
    }

    private function juntarPadraoDoPosto(): array
    {
        $guardado = (array) (PosSession::find(session('pos_id'))?->juntar_padrao ?? []);

        return array_map('boolval', array_merge(
            PrintJobService::JUNTAR_POR_OMISSAO,
            array_intersect_key($guardado, PrintJobService::GRUPOS_JUNTOS)
        ));
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
