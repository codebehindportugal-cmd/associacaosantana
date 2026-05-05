<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Produto;
use App\Models\CaixaDiaria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosRestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('PosRest/Index', [
            'posNome' => session('pos_nome'),
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

        $mesas = Mesa::principais()
            ->ativas()
            ->with([
                'pedidos' => $pedidosAtivos,
                'submesas' => fn ($query) => $query->ativas()->with(['pedidos' => $pedidosAtivos])->orderBy('numero'),
            ])
            ->orderBy('numero')
            ->get();

        return Inertia::render('PosRest/Mesas', ['mesas' => $mesas]);
    }

    public function mesa(Mesa $mesa): Response
    {
        $mesa->load([
            'mesaPrincipal',
            'submesas' => fn ($query) => $query->ativas()
                ->with(['pedidos' => fn ($pedidoQuery) => $pedidoQuery
                    ->whereIn('estado', ['pendente', 'preparacao', 'pronto'])
                    ->with('items.produto')])
                ->orderBy('numero'),
        ]);

        $pedido = $mesa->pedidos()
            ->whereIn('estado', ['pendente', 'preparacao', 'pronto'])
            ->with('items.produto.categoria')
            ->first();

        $produtos = Produto::with('categoria')
            ->disponiveis()
            ->orderBy('nome')
            ->get()
            ->groupBy(fn ($produto) => $produto->categoria->nome ?? 'Outros');

        return Inertia::render('PosRest/Mesa', compact('mesa', 'pedido', 'produtos'));
    }

    public function novoPedido(Request $request, Mesa $mesa): RedirectResponse
    {
        $data = $request->validate([
            'lugares_ocupados' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! $this->caixaRestauranteAberta()) {
            return back()->withErrors(['mesa_id' => 'Abre a caixa do Restaurante no backoffice antes de abrir pedidos.']);
        }

        $mesa = Mesa::with('submesas')->findOrFail($mesa->id);
        $mesaPedido = $this->mesaParaPedido($mesa, $data['lugares_ocupados'] ?? null);

        $pedido = Pedido::create([
            'tipo' => 'restaurante',
            'estado' => 'pendente',
            'mesa_id' => $mesaPedido->id,
            'user_id' => null,
            'pos_id' => session('pos_id'),
        ]);

        $mesaPedido->update(['estado' => 'ocupada']);
        $mesaPedido->mesaPrincipal?->update(['estado' => 'ocupada']);

        return to_route('pos.rest.mesa', $mesaPedido)->with('pedido_id', $pedido->id);
    }

    public function adicionarItem(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate([
            'produto_id' => ['required', 'exists:produtos,id'],
            'quantidade' => ['required', 'integer', 'min:1'],
            'prioridade' => ['nullable', 'boolean'],
        ]);

        $produto = Produto::with('categoria')->findOrFail($data['produto_id']);

        $itemExistente = $pedido->items()
            ->where('produto_id', $produto->id)
            ->where('estado', 'pendente')
            ->where('prioridade', (bool) ($data['prioridade'] ?? false))
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
            ]);
        }

        $pedido->update(['total' => $pedido->fresh('items')->total_calculado]);

        return back();
    }

    public function removerItem(Pedido $pedido, PedidoItem $item): RedirectResponse
    {
        abort_unless($item->pedido_id === $pedido->id, 404);

        $item->delete();
        $pedido->update(['total' => $pedido->fresh('items')->total_calculado]);

        return back();
    }

    public function toggleUrgente(Pedido $pedido, PedidoItem $item): RedirectResponse
    {
        abort_unless($item->pedido_id === $pedido->id, 404);

        $item->update(['prioridade' => ! $item->prioridade]);

        return back();
    }

    public function fecharConta(Request $request, Pedido $pedido): RedirectResponse
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

        return to_route('pos.rest.pedido.talao', $pedido);
    }

    public function talao(Pedido $pedido): Response
    {
        return Inertia::render('PosRest/Talao', [
            'pedido' => $pedido->load('mesa.mesaPrincipal', 'items.produto.categoria'),
        ]);
    }

    public function atualizarEstado(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate(['estado' => ['required', 'in:pendente,preparacao,pronto,entregue,cancelado']]);
        $pedido->update($data);
        if (in_array($pedido->estado, ['entregue', 'cancelado'], true)) {
            $this->libertarMesaDoPedido($pedido);
        }

        return back();
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

    private function caixaRestauranteAberta(): bool
    {
        return CaixaDiaria::whereDate('data', today())
            ->where('ponto', 'Restaurante')
            ->where('estado', 'aberta')
            ->exists();
    }

    private function mesaParaPedido(Mesa $mesa, ?int $lugaresOcupados): Mesa
    {
        if (! $lugaresOcupados || $lugaresOcupados >= $mesa->capacidade) {
            if (! $mesa->mesa_principal_id && ! $this->temPedidosAtivosNasSubmesas($mesa)) {
                $this->normalizarMesa($mesa);

                return $mesa->refresh();
            }

            return $mesa;
        }

        if ($mesa->mesa_principal_id) {
            return $this->dividirBlocoParaPedido($mesa, $lugaresOcupados);
        }

        if ($this->temPedidosAtivosNasSubmesas($mesa)) {
            return $mesa;
        }

        return $this->dividirBlocoParaPedido($mesa, $lugaresOcupados);
    }

    private function dividirBlocoParaPedido(Mesa $mesa, int $lugaresOcupados): Mesa
    {
        if ($mesa->mesa_principal_id && ($mesa->estado !== 'livre' || $this->temPedidosAtivosNaMesa($mesa))) {
            return $mesa;
        }

        $mesaPrincipal = $mesa->mesaPrincipal ?: $mesa;
        $inicio = (int) ($mesa->lugares_inicio ?: 1);
        $fim = (int) ($mesa->lugares_fim ?: $mesa->capacidade);
        $capacidade = $fim - $inicio + 1;

        if ($lugaresOcupados >= $capacidade) {
            return $mesa;
        }

        if ($mesa->mesa_principal_id) {
            Pedido::where('mesa_id', $mesa->id)->update(['mesa_id' => $mesaPrincipal->id]);
            $mesa->delete();
        } else {
            $mesa->submesas()->delete();
        }

        $fimOcupado = $inicio + $lugaresOcupados - 1;
        $ocupada = $this->criarSubmesa($mesaPrincipal, $inicio, $fimOcupado);

        if ($fimOcupado < $fim) {
            $this->criarSubmesa($mesaPrincipal, $fimOcupado + 1, $fim);
        }

        $mesaPrincipal->update(['estado' => 'ocupada']);

        return $ocupada;
    }

    private function criarSubmesa(Mesa $mesaPrincipal, int $inicio, int $fim): Mesa
    {
        $numero = $this->proximoNumeroSubmesa($mesaPrincipal);
        $indice = max(1, $numero - ($mesaPrincipal->numero * 100));

        return $mesaPrincipal->submesas()->create([
            'numero' => $numero,
            'nome' => 'Mesa '.$mesaPrincipal->numero.$this->letraSubmesa($indice),
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

    private function letraSubmesa(int $indice): string
    {
        return $indice <= 26 ? chr(64 + $indice) : '-'.$indice;
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

        if (! $this->temPedidosAtivosNaMesaCompleta($mesaPrincipal)) {
            $this->normalizarMesa($mesaPrincipal);

            return;
        }

        $mesaPrincipal->update(['estado' => 'ocupada']);
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

        $mesa->update(['estado' => 'livre']);
    }
}
