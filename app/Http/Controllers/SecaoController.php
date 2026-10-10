<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Mesa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SecaoController extends Controller
{
    public function bebidas(): Response
    {
        return $this->ecra('bebidas', 'BEBIDAS');
    }

    public function cozinha(): Response
    {
        return $this->comida();
    }

    public function comida(): Response
    {
        return $this->ecra('comida', 'COMIDA');
    }

    public function frango(): Response
    {
        return $this->ecra('frango', 'FRANGO');
    }

    public function sobremesas(): Response
    {
        return $this->ecra('sobremesas', 'SOBREMESAS');
    }

    public function acompanhamentos(): Response
    {
        return $this->ecra('acompanhamentos', 'ACOMPANHAMENTOS');
    }

    public function servico(): Response
    {
        return $this->comida();
    }

    /**
     * Ecra unico para festas em tasquinha: tudo menos bebidas (comida, frango,
     * acompanhamentos e sobremesas saem todos no mesmo sitio).
     */
    public function tasquinhas(): Response
    {
        return $this->ecra(self::TASQUINHAS, 'CARVALHAL FEST', 'tasquinhas');
    }

    private const TASQUINHAS = ['comida', 'frango', 'acompanhamentos', 'sobremesas'];

    public function bar(): Response
    {
        $items = Pedido::with('items.produto', 'user', 'pos')
            ->barPrepago()
            ->where('estado', 'pronto')
            ->whereDate('created_at', today())
            ->orderBy('numero_senha')
            ->get()
            ->map(fn ($pedido) => [
                'pedido_id' => $pedido->id,
                'mesa' => 'Senha #'.$pedido->codigo_senha,
                'operador' => $this->operadorPedido($pedido),
                'urgente' => false,
                // Início da espera (ISO 8601) — o ecrã calcula os minutos
                'desde' => $pedido->created_at?->toISOString(),
                'items' => $pedido->items->values(),
            ])
            ->sortBy([['desde', 'asc']]);

        return Inertia::render('Secao/Ecra', [
            'titulo' => 'BAR',
            'itemsPorMesa' => $items->values(),
            'tem_urgentes' => false,
            'modoBar' => true,
            'secao' => 'bar',
            'agora' => now()->toISOString(),
        ]);
    }

    public function sala(string $codigo): Response
    {
        abort_unless(hash_equals((string) config('app.sala_ecra_codigo'), $codigo), 403);

        return Inertia::render('Secao/Sala', [
            'mesas' => $this->mesasParaMapa(),
        ]);
    }

    private function ecra(string|array $secao, string $titulo, ?string $chave = null): Response
    {
        $secoes = (array) $secao;
        $chave ??= $secoes[0];

        $items = PedidoItem::with('pedido.mesa', 'pedido.user', 'pedido.pos', 'produto')
            ->whereHas('pedido', fn ($query) => $this->pedidosDoEcra($query, $secoes))
            ->whereIn('secao', $secoes)
            ->where('estado', 'pendente')
            ->orderByDesc('prioridade')
            ->oldest()
            ->get()
            ->groupBy(fn ($item) => $item->pedido->tipo === 'bar_prepago'
                ? 'Senha #'.$item->pedido->codigo_senha
                : ($item->pedido->mesa?->designacao ?? 'Para levar #'.$item->pedido_id))
            ->map(fn ($grupo, $mesa) => [
                'mesa' => $mesa,
                'operador' => $this->operadorPedido($grupo->first()->pedido),
                'urgente' => $grupo->contains('prioridade', true),
                // Início da espera (ISO 8601): artigo pendente mais antigo do grupo
                'desde' => $grupo->min('created_at')?->toISOString(),
                'items' => $grupo->sortByDesc('prioridade')->values(),
            ])
            // Prioridade primeiro, depois os mais antigos
            ->sortBy([['urgente', 'desc'], ['desde', 'asc']]);

        return Inertia::render('Secao/Ecra', [
            'titulo' => $titulo,
            'secao' => $chave,
            'mostrarSecao' => count($secoes) > 1,
            'itemsPorMesa' => $items->values(),
            'tem_urgentes' => PedidoItem::urgentes()
                ->whereHas('pedido', fn ($query) => $this->pedidosDoEcra($query, $secoes))
                ->whereIn('secao', $secoes)
                ->exists(),
            'agora' => now()->toISOString(),
        ]);
    }

    /**
     * Pedidos que aparecem no ecra de uma seccao: os do restaurante e, no
     * ecra da comida, tambem as senhas do bar. Assim a cozinha comeca a
     * preparar logo que a senha e vendida, antes de o cliente chegar ao
     * balcao. So entram senhas das ultimas 3 horas que ainda nao foram
     * retiradas nem canceladas.
     */
    private function pedidosDoEcra($query, array $secoes)
    {
        return $query->where(function ($q) use ($secoes) {
            $q->where('tipo', 'restaurante');

            if (in_array('comida', $secoes, true)) {
                $q->orWhere(fn ($bar) => $bar
                    ->where('tipo', 'bar_prepago')
                    ->whereNotIn('estado', ['entregue', 'cancelado'])
                    ->where('created_at', '>=', now()->subHours(3)));
            }
        });
    }

    public function pronto(Request $request, PedidoItem $pedidoItem): RedirectResponse
    {
        $data = $request->validate([
            'quantidade' => ['nullable', 'integer', 'min:1'],
        ]);

        $pedidoItem->load('pedido.mesa', 'produto');
        $quantidadePronta = min((int) ($data['quantidade'] ?? $pedidoItem->quantidade), (int) $pedidoItem->quantidade);

        if ($quantidadePronta < $pedidoItem->quantidade) {
            DB::transaction(function () use ($pedidoItem, $quantidadePronta) {
                // Primeiro reduz e so depois cria a parte pronta: para o stock
                // o total fica igual, mesmo que o produto ja esteja a zero.
                $itemPronto = $pedidoItem->replicate();
                $pedidoItem->decrement('quantidade', $quantidadePronta);

                $itemPronto->quantidade = $quantidadePronta;
                $itemPronto->estado = 'pronto';
                $itemPronto->save();
            });
        } else {
            $pedidoItem->update(['estado' => 'pronto']);
        }

        if (strcasecmp($pedidoItem->produto?->nome, 'Limpar mesa') === 0) {
            $pedidoItem->pedido->update(['estado' => 'entregue']);
            $this->libertarMesaDoPedido($pedidoItem->pedido);
        }

        return back();
    }

    /**
     * Limpa o ecra de uma seccao (ex.: pedidos que ficaram por marcar de
     * outro dia). Nao apaga nada: os artigos pendentes passam a "pronto"
     * e, no ecra do bar, as senhas passam a "entregue".
     */
    public function limpar(string $secao): RedirectResponse
    {
        abort_unless(in_array($secao, ['bar', 'bebidas', 'comida', 'frango', 'sobremesas', 'acompanhamentos', 'tasquinhas'], true), 404);
        $secoes = $secao === 'tasquinhas' ? self::TASQUINHAS : [$secao];

        if ($secao === 'bar') {
            Pedido::barPrepago()->where('estado', 'pronto')->update(['estado' => 'entregue']);

            return back();
        }

        PedidoItem::whereIn('secao', $secoes)
            ->where('estado', 'pendente')
            ->whereHas('pedido', fn ($query) => $this->pedidosDoEcra($query, $secoes))
            ->update(['estado' => 'pronto']);

        return back();
    }

    public function retirar(Pedido $pedido): RedirectResponse
    {
        $pedido->update(['estado' => 'entregue']);
        $this->libertarMesaDoPedido($pedido);

        return back();
    }

    private function libertarMesaDoPedido(Pedido $pedido): void
    {
        $mesa = $pedido->fresh('mesa.mesaPrincipal')?->mesa;

        if (! $mesa) {
            return;
        }

        $mesaPrincipal = $mesa->mesaPrincipal ?: $mesa;
        $mesaPrincipal->load('submesas');

        foreach ($mesaPrincipal->submesas as $submesa) {
            $submesa->update([
                'estado' => $this->temPedidosAtivosNaMesa($submesa) ? 'ocupada' : 'livre',
            ]);
        }

        if (! $this->temPedidosAtivosNaMesaCompleta($mesaPrincipal)) {
            $this->normalizarMesa($mesaPrincipal);

            return;
        }

        $mesaPrincipal->update(['estado' => 'ocupada']);
    }

    private function operadorPedido(Pedido $pedido): string
    {
        return $pedido->operador_nome ?: ($pedido->user?->name ?: ($pedido->pos?->nome ?: 'Sem operador'));
    }

    private function temPedidosAtivosNaMesa(Mesa $mesa): bool
    {
        return $mesa->pedidos()
            ->whereIn('estado', ['pendente', 'preparacao', 'pronto'])
            ->exists();
    }

    private function temPedidosAtivosNaMesaCompleta(Mesa $mesa): bool
    {
        return $this->temPedidosAtivosNaMesa($mesa)
            || $mesa->submesas()
                ->whereHas('pedidos', fn ($query) => $query->whereIn('estado', ['pendente', 'preparacao', 'pronto']))
                ->exists();
    }

    private function normalizarMesa(Mesa $mesa): void
    {
        $mesa->load('submesas');
        $submesaIds = $mesa->submesas->pluck('id');

        if ($submesaIds->isNotEmpty()) {
            Pedido::whereIn('mesa_id', $submesaIds)->update(['mesa_id' => $mesa->id]);
            $mesa->submesas()->delete();
        }

        $mesa->update(['estado' => 'livre']);
    }

    private function mesasParaMapa()
    {
        return Mesa::principais()
            ->ativas()
            ->with([
                'pedidos' => fn ($query) => $query
                    ->whereIn('estado', ['pendente', 'preparacao', 'pronto'])
                    ->latest()
                    ->select('id', 'mesa_id', 'estado', 'created_at', 'operador_nome'),
                'pedidosGrupo' => fn ($query) => $query
                    ->whereIn('pedidos.estado', ['pendente', 'preparacao', 'pronto'])
                    ->latest('pedidos.created_at')
                    ->select('pedidos.id', 'pedidos.mesa_id', 'pedidos.estado', 'pedidos.created_at', 'pedidos.operador_nome'),
                'submesas' => fn ($query) => $query
                    ->ativas()
                    ->with([
                        'pedidos' => fn ($pedidoQuery) => $pedidoQuery
                            ->whereIn('estado', ['pendente', 'preparacao', 'pronto'])
                            ->latest()
                            ->select('id', 'mesa_id', 'estado', 'created_at', 'operador_nome'),
                        'pedidosGrupo' => fn ($pedidoQuery) => $pedidoQuery
                            ->whereIn('pedidos.estado', ['pendente', 'preparacao', 'pronto'])
                            ->latest('pedidos.created_at')
                            ->select('pedidos.id', 'pedidos.mesa_id', 'pedidos.estado', 'pedidos.created_at', 'pedidos.operador_nome'),
                    ])
                    ->withCount('pedidos'),
            ])
            ->withCount('pedidos')
            ->orderBy('numero')
            ->get();
    }
}
