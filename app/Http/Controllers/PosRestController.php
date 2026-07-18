<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Produto;
use App\Models\CaixaDiaria;
use App\Services\PrintJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PosRestController extends Controller
{
    public function index(): Response
    {
        $pedidosAtivos = fn ($query) => $query
            ->whereIn('estado', ['pendente', 'preparacao', 'pronto'])
            ->with('items.produto');
        $pedidosGrupoAtivos = fn ($query) => $query
            ->whereIn('pedidos.estado', ['pendente', 'preparacao', 'pronto'])
            ->with('items.produto');

        $mesas = Mesa::principais()
            ->ativas()
            ->with([
                'pedidos' => $pedidosAtivos,
                'pedidosGrupo' => $pedidosGrupoAtivos,
                'submesas' => fn ($query) => $query->ativas()->with([
                    'pedidos' => $pedidosAtivos,
                    'pedidosGrupo' => $pedidosGrupoAtivos,
                ])->orderBy('numero'),
            ])
            ->orderBy('numero')
            ->get();

        $zonas = \App\Models\ZonaMapa::all();

        return Inertia::render('PosRest/Index', [
            'posNome' => session('pos_nome'),
            'mesas' => $mesas,
            'zonas' => $zonas,
            'vendasHoje' => Pedido::whereDate('created_at', today())->where('tipo', 'restaurante')->sum('total'),
            'mesasLivres' => Mesa::ativas()->where('estado', 'livre')->count(),
            'mesasOcupadas' => Mesa::ativas()->where('estado', 'ocupada')->count(),
        ]);
    }

    public function mesas(): Response
    {
        $pedidosAtivos = fn ($query) => $query
            ->whereIn('estado', ['pendente', 'preparacao', 'pronto'])
            ->with('items.produto');
        $pedidosGrupoAtivos = fn ($query) => $query
            ->whereIn('pedidos.estado', ['pendente', 'preparacao', 'pronto'])
            ->with('items.produto');

        $reservasAtivas = \App\Models\Reserva::whereDate('data', today())
            ->where('estado', 'sentada')
            ->whereNotNull('mesa_atribuida')
            ->get(['id', 'nome', 'pessoas', 'mesa_atribuida'])
            ->groupBy(fn ($r) => (int) preg_replace('/\D/', '', $r->mesa_atribuida));

        $mesas = Mesa::principais()
            ->ativas()
            ->with([
                'pedidos' => $pedidosAtivos,
                'pedidosGrupo' => $pedidosGrupoAtivos,
                'submesas' => fn ($query) => $query->ativas()->with([
                    'pedidos' => $pedidosAtivos,
                    'pedidosGrupo' => $pedidosGrupoAtivos,
                ])->orderBy('numero'),
            ])
            ->orderBy('numero')
            ->get()
            ->each(function ($mesa) use ($reservasAtivas) {
                $res = $reservasAtivas->get($mesa->numero)?->first();
                $mesa->setAttribute('reserva_ativa', $res
                    ? ['nome' => $res->nome, 'pessoas' => $res->pessoas, 'mesa_atribuida' => $res->mesa_atribuida]
                    : null
                );
            });

        $pedidosFechadosHoje = Pedido::where('tipo', 'restaurante')
            ->where('estado', 'entregue')
            ->whereDate('updated_at', today())
            ->with('mesa.mesaPrincipal')
            ->latest('updated_at')
            ->limit(30)
            ->get(['id', 'mesa_id', 'total', 'metodo_pagamento', 'observacoes', 'updated_at']);

        return Inertia::render('PosRest/Mesas', [
            'mesas' => $mesas,
            'pedidosFechadosHoje' => $pedidosFechadosHoje,
        ]);
    }

    public function mesa(Mesa $mesa): RedirectResponse|Response
    {
        // Se esta mesa não tem pedidos próprios mas é parte de um grupo,
        // redirecionar para a mesa onde a conta está registada.
        $temPedidoProprio = $mesa->pedidos()
            ->whereIn('estado', ['pendente', 'preparacao', 'pronto'])
            ->exists();

        if (! $temPedidoProprio) {
            $pedidoGrupo = $mesa->pedidosGrupo()
                ->whereIn('pedidos.estado', ['pendente', 'preparacao', 'pronto'])
                ->with('mesa.mesaPrincipal')
                ->first();

            if ($pedidoGrupo) {
                // A conta está na mesa principal do pedido — redirecionar para lá
                $mesaDaConta = $pedidoGrupo->mesa?->mesaPrincipal ?: $pedidoGrupo->mesa;
                if ($mesaDaConta && $mesaDaConta->id !== $mesa->id) {
                    return to_route('pos.rest.mesa', $mesaDaConta);
                }
            }
        }

        $mesa->load([
            'mesaPrincipal',
            'submesas' => fn ($query) => $query->ativas()
                ->with([
                    'pedidos' => fn ($pedidoQuery) => $pedidoQuery
                        ->whereIn('estado', ['pendente', 'preparacao', 'pronto'])
                        ->with('items.produto'),
                    'pedidosGrupo' => fn ($pedidoQuery) => $pedidoQuery
                        ->whereIn('pedidos.estado', ['pendente', 'preparacao', 'pronto'])
                        ->with('items.produto'),
                ])
                ->orderBy('numero'),
            'pedidosGrupo' => fn ($pedidoQuery) => $pedidoQuery
                ->whereIn('pedidos.estado', ['pendente', 'preparacao', 'pronto'])
                ->with('user', 'pos', 'items.produto.categoria'),
        ]);

        $pedido = $mesa->pedidos()
            ->whereIn('estado', ['pendente', 'preparacao', 'pronto'])
            ->with('user', 'pos', 'items.produto.categoria')
            ->first() ?: $mesa->pedidosGrupo->first();
        $pedido?->makeVisible('cliente_token');

        $produtos = Produto::with('categoria')
            ->disponiveisRestaurante()
            ->orderBy('nome')
            ->get()
            ->groupBy(fn ($produto) => $produto->categoria->nome ?? 'Outros');

        $mesasLivres = Mesa::principais()
            ->ativas()
            ->where('id', '!=', $mesa->id)
            ->where('localizacao', $mesa->localizacao)
            ->where('estado', 'livre')
            ->whereDoesntHave('pedidos', fn ($q) => $q->whereIn('estado', ['pendente', 'preparacao', 'pronto']))
            ->whereDoesntHave('submesas.pedidos', fn ($q) => $q->whereIn('estado', ['pendente', 'preparacao', 'pronto']))
            ->orderBy('numero')
            ->get(['id', 'numero', 'capacidade', 'localizacao']);

        // Anexar reserva_ativa — mesma lógica de mesas(), filtro em PHP para compatibilidade MySQL 5.7
        $reservaAtiva = \App\Models\Reserva::whereDate('data', today())
            ->where('estado', 'sentada')
            ->whereNotNull('mesa_atribuida')
            ->get(['id', 'nome', 'pessoas', 'mesa_atribuida'])
            ->first(fn ($r) => (int) preg_replace('/\D/', '', $r->mesa_atribuida) === (int) $mesa->numero);
        $mesa->setAttribute('reserva_ativa', $reservaAtiva
            ? ['nome' => $reservaAtiva->nome, 'pessoas' => $reservaAtiva->pessoas, 'mesa_atribuida' => $reservaAtiva->mesa_atribuida]
            : null
        );

        return Inertia::render('PosRest/Mesa', compact('mesa', 'pedido', 'produtos', 'mesasLivres'));
    }

    public function novoPedido(Request $request, Mesa $mesa): RedirectResponse
    {
        $data = $request->validate([
            'lugares_ocupados' => ['required', 'integer', 'min:1'],
            'submesa_letra' => ['nullable', 'string', 'regex:/^[A-Da-d]$/'],
            'mesas_grupo' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $this->caixaRestauranteAberta()) {
            return back()->withErrors(['mesa_id' => 'Abre a caixa do Restaurante no backoffice antes de abrir pedidos.']);
        }

        $mesa = Mesa::with('submesas')->findOrFail($mesa->id);

        // Se é uma submesa livre, redirecionar para a mesa principal
        // para suportar fusão de blocos livres contíguos
        if ($mesa->mesa_principal_id) {
            $mesa = Mesa::with('submesas')->findOrFail($mesa->mesa_principal_id);
        }

        $lugares      = (int) $data['lugares_ocupados'];
        $letraSubmesa = $data['submesa_letra'] ?? null;
        $capacidadeMesa = $this->capacidadeFisicaMesa($mesa);
        $mesasGrupo = collect();

        if (! $mesa->mesa_principal_id && $lugares < $capacidadeMesa && empty($letraSubmesa) && ! $this->temPedidosAtivosNasSubmesas($mesa)) {
            return back()->withErrors(['submesa_letra' => 'Indica a letra da submesa para pedidos com menos lugares do que a mesa completa.']);
        }

        if ($lugares > $capacidadeMesa && empty($data['mesas_grupo'])) {
            return back()->withErrors(['mesas_grupo' => 'Indica as mesas do grupo para pedidos com mais lugares do que a mesa inicial.']);
        }

        if ($lugares > $capacidadeMesa && empty($letraSubmesa)) {
            return back()->withErrors(['submesa_letra' => 'Indica a letra do grupo (A, B, C ou D).']);
        }

        if ($lugares > $capacidadeMesa) {
            $mesasGrupo = $this->mesasGrupoPorNumeros($mesa, $data['mesas_grupo'], $lugares);

            if ($mesasGrupo->isEmpty()) {
                // Diagnose: check if table numbers exist
                $numerosEspecificados = collect(preg_split('/[\s,]+/', $data['mesas_grupo']))
                    ->map(fn ($n) => (int) trim((string) $n))->filter()->values();
                $mesasEncontradas = Mesa::principais()->ativas()->whereIn('numero', $numerosEspecificados)->get();
                if ($mesasEncontradas->count() !== $numerosEspecificados->count()) {
                    $naoEncontradas = $numerosEspecificados->diff($mesasEncontradas->pluck('numero'))->values();
                    return back()->withErrors(['mesas_grupo' => 'Mesa(s) não encontrada(s) ou inativa(s): ' . $naoEncontradas->join(', ')]);
                }
                $ocupadas = $mesasEncontradas->filter(fn ($m) => (int) $m->id !== (int) $mesa->id && !$this->mesaDisponivelParaGrupo($m))->pluck('numero');
                if ($ocupadas->isNotEmpty()) {
                    return back()->withErrors(['mesas_grupo' => 'Mesa(s) ocupada(s) ou com pedido ativo: ' . $ocupadas->join(', ')]);
                }
                $capacidadeTotal = $mesasEncontradas->sum(fn ($m) => $this->capacidadeFisicaMesa($m)) + $capacidadeMesa;
                return back()->withErrors(['mesas_grupo' => "Capacidade insuficiente: {$capacidadeTotal} lugares para {$lugares} pessoas. Adiciona mais mesas ao grupo."]);
            }
            if ($mesasGrupo->sum('capacidade') < $lugares) {
                return back()->withErrors(['lugares_ocupados' => 'Nao existem mesas livres suficientes para este grupo.']);
            }

            $mesaPedido = $mesa;
        } else {
            $mesaPedido = $this->mesaParaPedido($mesa, $data['lugares_ocupados'] ?? null, $letraSubmesa);
        }

        $pedido = Pedido::create([
            'tipo' => 'restaurante',
            'estado' => 'pendente',
            'mesa_id' => $mesaPedido->id,
            'user_id' => null,
            'pos_id' => session('pos_id'),
            'operador_nome' => session('pos_operador') ?: session('pos_nome'),
        ]);

        $mesaPedido->update(['estado' => 'ocupada']);
        $mesaPedido->mesaPrincipal?->update(['estado' => 'ocupada']);

        if ($mesasGrupo->isNotEmpty()) {
            $pedido->mesasGrupo()->sync($mesasGrupo->pluck('id'));
            Mesa::whereIn('id', $mesasGrupo->pluck('id'))->update(['estado' => 'ocupada']);
        }

        return to_route('pos.rest.mesa', $mesaPedido)->with('pedido_id', $pedido->id);
    }

    public function adicionarItem(Request $request, Pedido $pedido, PrintJobService $printJobs): RedirectResponse
    {
        $data = $request->validate([
            'produto_id' => ['required', $this->produtoRestauranteRule()],
            'quantidade' => ['required', 'integer', 'min:1'],
            'prioridade' => ['nullable', 'boolean'],
            'observacoes' => ['nullable', 'string', 'max:255'],
        ]);

        $produto = Produto::with('categoria')->findOrFail($data['produto_id']);
        $observacoes = trim((string) ($data['observacoes'] ?? '')) ?: null;

        $itemExistente = $pedido->items()
            ->where('produto_id', $produto->id)
            ->where('estado', 'pendente')
            ->where('prioridade', (bool) ($data['prioridade'] ?? false))
            ->where('observacoes', $observacoes)
            ->first();

        if ($itemExistente) {
            $itemExistente->increment('quantidade', (int) $data['quantidade']);
        } else {
            $pedido->items()->create([
                'produto_id' => $produto->id,
                'quantidade' => $data['quantidade'],
                'preco_unitario' => $produto->preco,
                'secao' => $this->normalizarSecao($produto->categoria->secao ?? 'cozinha'),
                'prioridade' => (bool) ($data['prioridade'] ?? false),
                'observacoes' => $observacoes,
            ]);
        }

        $pedido->update(['total' => $pedido->fresh('items')->total_calculado]);
        $secao = $this->normalizarSecao($produto->categoria->secao ?? 'cozinha');
        $printJobs->criarItemPedido($pedido->fresh('mesa.mesaPrincipal', 'user', 'pos'), [
            'quantidade' => (int) $data['quantidade'],
            'nome' => $produto->nome,
            'observacoes' => $observacoes,
            'prioridade' => (bool) ($data['prioridade'] ?? false),
        ], $secao);

        return back()->with('success', 'Pedido validado e enviado para a equipa.');
    }

    public function adicionarItems(Request $request, Pedido $pedido, PrintJobService $printJobs): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.produto_id' => ['required', $this->produtoRestauranteRule()],
            'items.*.quantidade' => ['required', 'integer', 'min:1'],
            'items.*.prioridade' => ['nullable', 'boolean'],
            'items.*.observacoes' => ['nullable', 'string', 'max:255'],
        ]);

        $produtos = Produto::with('categoria')
            ->whereIn('id', collect($data['items'])->pluck('produto_id'))
            ->get()
            ->keyBy('id');

        $itemsParaImpressao = collect();

        foreach ($data['items'] as $item) {
            $produto = $produtos[(int) $item['produto_id']];
            $secao = $this->normalizarSecao($produto->categoria->secao ?? 'cozinha');
            $quantidade = (int) $item['quantidade'];
            $prioridade = (bool) ($item['prioridade'] ?? false);
            $observacoes = trim((string) ($item['observacoes'] ?? '')) ?: null;

            $this->guardarItemPedido($pedido, $produto, $quantidade, $secao, $prioridade, $observacoes);

            $itemsParaImpressao->push([
                'quantidade' => $quantidade,
                'nome' => $produto->nome,
                'observacoes' => $observacoes,
                'prioridade' => $prioridade,
                'secao' => $secao,
            ]);
        }

        $pedido->update(['total' => $pedido->fresh('items')->total_calculado]);

        // Agrupar itens por secção: um único talão por secção/impressora
        $pedidoFresh = $pedido->fresh('mesa.mesaPrincipal', 'user', 'pos');
        $itemsParaImpressao
            ->groupBy('secao')
            ->each(function ($itensDaSecao, $secao) use ($pedidoFresh, $printJobs) {
                $itensCombinados = $this->agruparItemsImpressao($itensDaSecao->all());
                $printJobs->criarPedido($pedidoFresh, $secao, 'pedido', $itensCombinados);
            });

        return back();
    }

    public function removerItem(Pedido $pedido, PedidoItem $item, PrintJobService $printJobs): RedirectResponse
    {
        abort_unless($item->pedido_id === $pedido->id, 404);
        if (! $this->itemPodeSerAnulado($item)) {
            return back()->withErrors(['item' => 'Este item ja passou os 2 minutos. A anulacao so pode ser feita no backoffice.']);
        }

        $item->loadMissing('produto.categoria');
        $quantidadeRetirada = 1;
        $secao = $this->normalizarSecao($item->secao ?: ($item->produto?->categoria?->secao ?? 'cozinha'));

        if ($item->quantidade > $quantidadeRetirada) {
            $item->decrement('quantidade', $quantidadeRetirada);
        } else {
            $item->delete();
        }

        $pedido->update(['total' => $pedido->fresh('items')->total_calculado]);
        $printJobs->criarAnulacaoItemPedido($pedido->fresh('mesa.mesaPrincipal', 'user', 'pos'), [
            'quantidade' => $quantidadeRetirada,
            'nome' => $item->produto?->nome ?? 'Produto',
            'observacoes' => $item->observacoes,
        ], $secao);

        return back();
    }

    public function toggleUrgente(Pedido $pedido, PedidoItem $item): RedirectResponse
    {
        abort_unless($item->pedido_id === $pedido->id, 404);

        $item->update(['prioridade' => ! $item->prioridade]);

        return back();
    }

    public function fecharConta(Request $request, Pedido $pedido, PrintJobService $printJobs): RedirectResponse
    {
        $data = $request->validate([
            'metodo_pagamento' => ['required', 'in:dinheiro,mbway,multibanco'],
            'valor_recebido' => ['nullable', 'numeric', 'min:0'],
            'troco' => ['nullable', 'numeric', 'min:0'],
        ]);

        $total = round($pedido->fresh('items')->total_calculado, 2);
        $valorRecebido = round((float) ($data['valor_recebido'] ?? $total), 2);
        $troco = round((float) ($data['troco'] ?? 0), 2);

        if ($valorRecebido < $total) {
            return back()->withErrors(['valor_recebido' => 'O valor recebido nao pode ser inferior ao total.']);
        }

        $pedido->update([
            'estado' => 'entregue',
            'total' => $total,
            'valor_recebido' => $valorRecebido,
            'troco' => $troco,
            'doacao' => max(0, round($valorRecebido - $total - $troco, 2)),
            'metodo_pagamento' => $data['metodo_pagamento'],
        ]);

        $this->libertarMesaDoPedido($pedido);
        $printJobs->criarConta($pedido->fresh('mesa.mesaPrincipal', 'user', 'pos', 'items.produto.categoria'));

        return to_route('pos.rest.pedido.talao', $pedido);
    }

    public function atualizarObservacoes(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate([
            'observacoes' => ['nullable', 'string', 'max:500'],
        ]);

        $pedido->update(['observacoes' => $data['observacoes'] ?? null]);

        return back()->with('success', 'Observação guardada.');
    }

    public function atualizarLugares(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate([
            'lugares_ocupados' => ['required', 'integer', 'min:1'],
        ]);

        $mesa = $pedido->mesa;

        if (! $mesa) {
            return back()->withErrors(['lugares_ocupados' => 'Este pedido nao tem mesa associada.']);
        }

        $lugares = min((int) $data['lugares_ocupados'], (int) ($mesa->capacidade ?: $data['lugares_ocupados']));
        $inicio = (int) ($mesa->lugares_inicio ?: 1);

        $campos = ['lugares_fim' => $inicio + $lugares - 1];

        // So actualiza a capacidade em submesas; nas mesas principais nao alterar
        // a capacidade fisica para nao corromper o valor ao libertar a mesa
        if ($mesa->mesa_principal_id) {
            $campos['capacidade'] = $lugares;
        }

        $mesa->update($campos);

        return back();
    }

    public function talao(Pedido $pedido): Response
    {
        return Inertia::render('PosRest/Talao', [
            'pedido' => $pedido->load('mesa.mesaPrincipal', 'user', 'pos', 'items.produto.categoria'),
        ]);
    }

    public function atualizarEstado(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate(['estado' => ['required', 'in:pendente,preparacao,entregue,cancelado']]);
        $pedido->load('mesa.mesaPrincipal');
        $mesaDestino = $pedido->mesa?->mesaPrincipal ?: $pedido->mesa;

        $pedido->update($data);

        if (in_array($pedido->estado, ['entregue', 'cancelado'], true)) {
            $this->libertarMesaDoPedido($pedido);

            return $mesaDestino
                ? to_route('pos.rest.mesa', $mesaDestino)->with('success', $pedido->estado === 'cancelado' ? 'Pedido cancelado e mesa libertada.' : 'Pedido concluido e mesa libertada.')
                : to_route('pos.rest.mesas')->with('success', $pedido->estado === 'cancelado' ? 'Pedido cancelado.' : 'Pedido concluido.');
        }

        return back()->with('success', 'Estado do pedido atualizado.');
    }

    public function pedidoExtra(Request $request, Mesa $mesa, PrintJobService $printJobs): RedirectResponse
    {
        $data = $request->validate([
            'descricao' => ['required', 'string', 'max:100'],
        ]);

        $mesa->load('mesaPrincipal');
        $printJobs->criarPedidoExtra($mesa, $data['descricao']);

        return back()->with('success', 'Pedido enviado: '.$data['descricao']);
    }

    public function historico(): Response
    {
        $pedidos = Pedido::where('pos_id', session('pos_id'))
            ->whereDate('created_at', today())
            ->with('mesa', 'items.produto')
            ->latest()
            ->get();

        return Inertia::render('PosRest/Historico', compact('pedidos'));
    }

    private function normalizarSecao(string $secao): string
    {
        return $secao;
    }

    private function guardarItemPedido(Pedido $pedido, Produto $produto, int $quantidade, string $secao, bool $prioridade, ?string $observacoes): void
    {
        $itemExistente = $pedido->items()
            ->where('produto_id', $produto->id)
            ->where('estado', 'pendente')
            ->where('prioridade', $prioridade)
            ->where('observacoes', $observacoes)
            ->first();

        if ($itemExistente) {
            $itemExistente->increment('quantidade', $quantidade);

            return;
        }

        $pedido->items()->create([
            'produto_id' => $produto->id,
            'quantidade' => $quantidade,
            'preco_unitario' => $produto->preco,
            'secao' => $secao,
            'prioridade' => $prioridade,
            'observacoes' => $observacoes,
        ]);
    }

    private function agruparItemsImpressao(array $items): array
    {
        return collect($items)
            ->groupBy(fn ($item) => ($item['nome'] ?? 'Produto').'|'.($item['observacoes'] ?? ''))
            ->map(fn ($grupo) => [
                'quantidade' => $grupo->sum('quantidade'),
                'nome' => $grupo->first()['nome'] ?? 'Produto',
                'observacoes' => $grupo->first()['observacoes'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function itemPodeSerAnulado(PedidoItem $item): bool
    {
        return $item->created_at?->greaterThanOrEqualTo(now()->subMinutes(2)) ?? false;
    }

    private function caixaRestauranteAberta(): bool
    {
        return CaixaDiaria::abertaParaPonto('Restaurante') !== null;
    }

    private function mesaParaPedido(Mesa $mesa, ?int $lugaresOcupados, ?string $letraSubmesa = null): Mesa
    {
        if (! $lugaresOcupados || $lugaresOcupados >= $mesa->capacidade) {
            if (! $mesa->mesa_principal_id && ! $this->temPedidosAtivosNasSubmesas($mesa)) {
                $this->normalizarMesa($mesa);

                return $mesa->refresh();
            }

            return $mesa;
        }

        if ($mesa->mesa_principal_id) {
            return $this->dividirBlocoParaPedido($mesa, $lugaresOcupados, $letraSubmesa);
        }

        if ($this->temPedidosAtivosNasSubmesas($mesa)) {
            // Tenta fundir blocos livres contíguos para acomodar o novo grupo
            $blocoLivre = $this->encontrarBlocoLivre($mesa, $lugaresOcupados);
            if ($blocoLivre) {
                return $this->dividirBlocoParaPedido($blocoLivre, $lugaresOcupados, $letraSubmesa);
            }

            return $mesa;
        }

        return $this->dividirBlocoParaPedido($mesa, $lugaresOcupados, $letraSubmesa);
    }

    private function dividirBlocoParaPedido(Mesa $mesa, int $lugaresOcupados, ?string $letraSubmesa = null): Mesa
    {
        if ($mesa->mesa_principal_id && ($mesa->estado !== 'livre' || $this->temPedidosAtivosNaMesa($mesa))) {
            return $mesa;
        }

        $mesaPrincipal = $mesa->mesaPrincipal ?: $mesa;
        $inicio = (int) ($mesa->lugares_inicio ?: 1);
        $fim = (int) ($mesa->lugares_fim ?: $mesa->capacidade);
        $capacidade = $fim - $inicio + 1;

        if ($lugaresOcupados >= $capacidade) {
            // Se foi fornecida uma letra e é uma submesa, renomear com essa letra
            if ($letraSubmesa && $mesa->mesa_principal_id) {
                $letraNormalizada = $this->normalizarLetraSubmesa($letraSubmesa);
                if ($letraNormalizada && ! $this->letraSubmesaEmUso($mesaPrincipal, $letraNormalizada)) {
                    $mesa->update(['nome' => 'Mesa '.$mesaPrincipal->numero.$letraNormalizada]);
                }
            }

            return $mesa;
        }

        if ($mesa->mesa_principal_id) {
            Pedido::where('mesa_id', $mesa->id)->update(['mesa_id' => $mesaPrincipal->id]);
            $mesa->delete();
        } else {
            $mesa->submesas()->delete();
        }

        $fimOcupado = $inicio + $lugaresOcupados - 1;
        $ocupada = $this->criarSubmesa($mesaPrincipal, $inicio, $fimOcupado, $letraSubmesa);

        if ($fimOcupado < $fim) {
            $this->criarSubmesa($mesaPrincipal, $fimOcupado + 1, $fim);
        }

        $mesaPrincipal->update(['estado' => 'ocupada']);

        return $ocupada;
    }

    private function criarSubmesa(Mesa $mesaPrincipal, int $inicio, int $fim, ?string $letra = null): Mesa
    {
        $numero = $this->proximoNumeroSubmesa($mesaPrincipal);
        $letra = $this->normalizarLetraSubmesa($letra);
        $letra = $letra && ! $this->letraSubmesaEmUso($mesaPrincipal, $letra)
            ? $letra
            : $this->proximaLetraSubmesa($mesaPrincipal);

        return $mesaPrincipal->submesas()->create([
            'numero' => $numero,
            'nome' => 'Mesa '.$mesaPrincipal->numero.$letra,
            'capacidade' => $fim - $inicio + 1,
            'lugares_inicio' => $inicio,
            'lugares_fim' => $fim,
            'localizacao' => $mesaPrincipal->localizacao,
            'estado' => 'livre',
            'ativa' => true,
        ]);
    }

    private function proximoNumeroSubmesa(Mesa $mesaPrincipal): int
    {
        $base = $mesaPrincipal->numero * 100;
        $ultimoNumero = (int) $mesaPrincipal->submesas()->max('numero');

        return $ultimoNumero > $base ? $ultimoNumero + 1 : $base + 1;
    }

    private function proximaLetraSubmesa(Mesa $mesaPrincipal): string
    {
        $usadas = $mesaPrincipal->submesas()
            ->pluck('nome')
            ->map(fn ($nome) => preg_replace('/^Mesa\s*'.preg_quote((string) $mesaPrincipal->numero, '/').'/i', '', (string) $nome))
            ->map(fn ($letra) => strtoupper(trim((string) $letra)))
            ->filter()
            ->values()
            ->all();

        foreach (range('A', 'D') as $letra) {
            if (! in_array($letra, $usadas, true)) {
                return $letra;
            }
        }

        return 'D';
    }

    private function normalizarLetraSubmesa(?string $letra): ?string
    {
        $letra = strtoupper(trim((string) $letra));

        return preg_match('/^[A-D]$/', $letra) ? $letra : null;
    }

    private function letraSubmesaEmUso(Mesa $mesaPrincipal, string $letra): bool
    {
        return $mesaPrincipal->submesas()
            ->pluck('nome')
            ->contains(fn ($nome) => strtoupper(trim((string) preg_replace('/^Mesa\s*'.preg_quote((string) $mesaPrincipal->numero, '/').'/i', '', (string) $nome))) === $letra);
    }

    private function libertarMesaDoPedido(Pedido $pedido): void
    {
        $mesa = $pedido->fresh('mesa.mesaPrincipal')?->mesa;

        if (! $mesa) {
            return;
        }

        $mesa->update([
            'estado' => $this->temPedidosAtivosNaMesa($mesa) ? 'ocupada' : 'livre',
        ]);

        $mesaPrincipal = $mesa->mesaPrincipal ?: $mesa;
        $mesaPrincipal->load('submesas');

        foreach ($mesaPrincipal->submesas as $submesa) {
            $submesa->update([
                'estado' => $this->temPedidosAtivosNaMesa($submesa) ? 'ocupada' : 'livre',
            ]);
        }

        $this->atualizarMesasGrupo($pedido);

        if (! $this->temPedidosAtivosNaMesaCompleta($mesaPrincipal) && ! $this->temPedidosGrupoAtivosNaMesaCompleta($mesaPrincipal)) {
            $this->normalizarMesa($mesaPrincipal);

            return;
        }

        // Fundir submesas livres contíguas numa só (evita fragmentação após fechar grupos)
        $this->fundirSubMesasLivres($mesaPrincipal);

        $mesaPrincipal->update(['estado' => 'ocupada']);
    }

    private function fundirSubMesasLivres(Mesa $mesaPrincipal): void
    {
        $livres = $mesaPrincipal->submesas()
            ->where('estado', 'livre')
            ->whereNotNull('lugares_inicio')
            ->whereDoesntHave('pedidos', fn ($q) => $q->whereIn('estado', ['pendente', 'preparacao', 'pronto']))
            ->orderBy('lugares_inicio')
            ->get();

        if ($livres->count() <= 1) {
            return;
        }

        $fundir = function (\Illuminate\Support\Collection $bloco) use ($mesaPrincipal): void {
            if ($bloco->count() <= 1) {
                return;
            }
            $inicio = (int) $bloco->first()->lugares_inicio;
            $fim    = (int) $bloco->last()->lugares_fim;
            $bloco->each->delete();
            $mesaPrincipal->submesas()->create([
                'numero'         => $this->proximoNumeroSubmesa($mesaPrincipal),
                'nome'           => 'Mesa '.$mesaPrincipal->numero,
                'capacidade'     => $fim - $inicio + 1,
                'lugares_inicio' => $inicio,
                'lugares_fim'    => $fim,
                'localizacao'    => $mesaPrincipal->localizacao,
                'estado'         => 'livre',
                'ativa'          => true,
            ]);
        };

        $blocoAtual = collect([$livres->first()]);

        foreach ($livres->slice(1) as $submesa) {
            if ((int) $submesa->lugares_inicio === (int) $blocoAtual->last()->lugares_fim + 1) {
                $blocoAtual->push($submesa);
            } else {
                $fundir($blocoAtual);
                $blocoAtual = collect([$submesa]);
            }
        }
        $fundir($blocoAtual);
    }

    private function atualizarMesasGrupo(Pedido $pedido): void
    {
        $mesasPrincipaisParaNormalizar = collect();

        $pedido->mesasGrupo()->each(function (Mesa $mesaGrupo) use ($mesasPrincipaisParaNormalizar) {
            $mesaGrupo->update([
                'estado' => $this->temPedidosAtivosNaMesa($mesaGrupo) || $mesaGrupo->pedidosGrupo()
                    ->whereIn('pedidos.estado', ['pendente', 'preparacao', 'pronto'])
                    ->exists() ? 'ocupada' : 'livre',
            ]);

            if ($mesaGrupo->mesa_principal_id) {
                $mesasPrincipaisParaNormalizar->push($mesaGrupo->mesaPrincipal);
            }
        });

        $mesasPrincipaisParaNormalizar
            ->filter()
            ->unique('id')
            ->each(function (Mesa $mesaPrincipal) {
                if (! $this->temPedidosAtivosNaMesaCompleta($mesaPrincipal) && ! $this->temPedidosGrupoAtivosNaMesaCompleta($mesaPrincipal)) {
                    $this->normalizarMesa($mesaPrincipal);
                }
            });
    }

    private function temPedidosGrupoAtivosNaMesaCompleta(Mesa $mesa): bool
    {
        return $mesa->pedidosGrupo()->whereIn('pedidos.estado', ['pendente', 'preparacao', 'pronto'])->exists()
            || $mesa->submesas()->whereHas('pedidosGrupo', fn ($query) => $query->whereIn('pedidos.estado', ['pendente', 'preparacao', 'pronto']))->exists();
    }

    private function temPedidosAtivosNaMesa(Mesa $mesa): bool
    {
        return $mesa->pedidos()
            ->whereIn('estado', ['pendente', 'preparacao', 'pronto'])
            ->exists();
    }

    private function temPedidosAtivosNasSubmesas(Mesa $mesa): bool
    {
        return $mesa->submesas()
            ->whereHas('pedidos', fn ($query) => $query->whereIn('estado', ['pendente', 'preparacao', 'pronto']))
            ->exists();
    }

    private function temPedidosAtivosNaMesaCompleta(Mesa $mesa): bool
    {
        return $this->temPedidosAtivosNaMesa($mesa)
            || $this->temPedidosAtivosNasSubmesas($mesa);
    }

    private function normalizarMesa(Mesa $mesa): void
    {
        $mesa->load('submesas');
        $submesaIds = $mesa->submesas->pluck('id');

        if ($submesaIds->isNotEmpty()) {
            Pedido::whereIn('mesa_id', $submesaIds)->update(['mesa_id' => $mesa->id]);
            $mesa->submesas()->delete();
        }

        $mesa->update([
            'estado' => 'livre',
            'lugares_inicio' => null,
            'lugares_fim' => null,
        ]);
    }

    private function mesasLivresParaGrupo(Mesa $mesaInicial, int $lugares): Collection
    {
        if (! $this->mesaDisponivelParaGrupo($mesaInicial)) {
            return collect();
        }

        $mesas = collect([$mesaInicial]);
        $lugaresRestantes = max(0, $lugares - $this->capacidadeFisicaMesa($mesaInicial));
        $this->normalizarMesa($mesaInicial);

        $candidatas = Mesa::principais()
            ->ativas()
            ->where('id', '!=', $mesaInicial->id)
            ->where('localizacao', $mesaInicial->localizacao)
            ->orderByRaw('ABS(numero - ?) ASC', [$mesaInicial->numero])
            ->get();

        foreach ($candidatas as $mesa) {
            if ($lugaresRestantes <= 0) {
                break;
            }

            if (! $this->mesaDisponivelParaGrupo($mesa)) {
                continue;
            }

            $capacidade = $this->capacidadeFisicaMesa($mesa);

            if ($lugaresRestantes >= $capacidade) {
                $this->normalizarMesa($mesa);
                $mesas->push($mesa->refresh());
                $lugaresRestantes -= $capacidade;

                continue;
            }

            $submesaOcupada = $this->dividirBlocoParaPedido($mesa, $lugaresRestantes);
            $mesas->push($submesaOcupada);
            $lugaresRestantes = 0;
        }

        if ($lugaresRestantes > 0) {
            $mesas->each(function (Mesa $mesa) use ($mesaInicial) {
                if ($mesa->id !== $mesaInicial->id && $mesa->mesa_principal_id) {
                    $this->normalizarMesa($mesa->mesaPrincipal);
                }
            });

            return collect();
        }

        return $mesas;
    }

    private function mesasGrupoPorNumeros(Mesa $mesaInicial, string $numeros, int $lugares): Collection
    {
        $listaNumeros = collect(preg_split('/[,\s]+/', $numeros))
            ->map(fn ($numero) => (int) trim((string) $numero))
            ->filter()
            ->prepend((int) $mesaInicial->numero)
            ->unique()
            ->values();

        if ($listaNumeros->isEmpty()) {
            return collect();
        }

        $mesas = Mesa::principais()
            ->ativas()
            ->whereIn('numero', $listaNumeros)
            ->get()
            ->sortBy(fn ($mesa) => $listaNumeros->search((int) $mesa->numero))
            ->values();

        if ($mesas->count() !== $listaNumeros->count()) {
            return collect();
        }

        if ($mesas->contains(fn (Mesa $mesa) => ! $this->mesaDisponivelParaGrupo($mesa) && (int) $mesa->id !== (int) $mesaInicial->id)) {
            return collect();
        }

        $lugaresRestantes = $lugares;
        $mesasGrupo = collect();

        foreach ($mesas as $mesa) {
            if ($lugaresRestantes <= 0) {
                break;
            }

            $capacidade = $this->capacidadeFisicaMesa($mesa);

            if ($lugaresRestantes >= $capacidade) {
                $this->normalizarMesa($mesa);
                $mesasGrupo->push($mesa->refresh());
                $lugaresRestantes -= $capacidade;

                continue;
            }

            $mesasGrupo->push($this->dividirBlocoParaPedido($mesa, $lugaresRestantes));
            $lugaresRestantes = 0;
        }

        return $lugaresRestantes > 0 ? collect() : $mesasGrupo;
    }

    private function mesaDisponivelParaGrupo(Mesa $mesa): bool
    {
        return ! $mesa->mesa_principal_id
            && $mesa->estado === 'livre'
            && ! $this->temPedidosAtivosNaMesa($mesa)
            && ! $this->temPedidosAtivosNasSubmesas($mesa)
            && ! $mesa->pedidosGrupo()->whereIn('pedidos.estado', ['pendente', 'preparacao', 'pronto'])->exists();
    }

    /**
     * Encontra o primeiro bloco contíguo de submesas livres com capacidade suficiente.
     * Se o bloco for composto por múltiplas submesas adjacentes, funde-as numa só.
     */
    private function encontrarBlocoLivre(Mesa $mesaPrincipal, int $lugaresNecessarios): ?Mesa
    {
        $livres = $mesaPrincipal->submesas()
            ->where('estado', 'livre')
            ->whereNotNull('lugares_inicio')
            ->whereDoesntHave('pedidos', fn ($q) => $q->whereIn('estado', ['pendente', 'preparacao', 'pronto']))
            ->orderBy('lugares_inicio')
            ->get();

        if ($livres->isEmpty()) {
            return null;
        }

        // Agrupar submesas livres em blocos contíguos
        $blocos      = [];
        $blocoAtual  = collect([$livres->first()]);

        foreach ($livres->slice(1) as $submesa) {
            if ((int) $submesa->lugares_inicio === (int) $blocoAtual->last()->lugares_fim + 1) {
                $blocoAtual->push($submesa);
            } else {
                $blocos[]   = $blocoAtual;
                $blocoAtual = collect([$submesa]);
            }
        }
        $blocos[] = $blocoAtual;

        // Devolver o primeiro bloco com capacidade suficiente (fundir se necessário)
        foreach ($blocos as $bloco) {
            $inicio = (int) $bloco->first()->lugares_inicio;
            $fim    = (int) $bloco->last()->lugares_fim;
            $cap    = $fim - $inicio + 1;

            if ($cap >= $lugaresNecessarios) {
                if ($bloco->count() > 1) {
                    $bloco->each->delete();

                    return $mesaPrincipal->submesas()->create([
                        'numero'         => $this->proximoNumeroSubmesa($mesaPrincipal),
                        'nome'           => 'Mesa '.$mesaPrincipal->numero,
                        'capacidade'     => $cap,
                        'lugares_inicio' => $inicio,
                        'lugares_fim'    => $fim,
                        'localizacao'    => $mesaPrincipal->localizacao,
                        'estado'         => 'livre',
                        'ativa'          => true,
                    ]);
                }

                return $bloco->first();
            }
        }

        return null;
    }

    private function capacidadeFisicaMesa(Mesa $mesa): int
    {
        return min(10, max(1, (int) $mesa->capacidade));
    }

    private function produtoRestauranteRule()
    {
        return Rule::exists('produtos', 'id')
            ->where('disponivel', true)
            ->where('disponivel_restaurante', true);
    }
}
