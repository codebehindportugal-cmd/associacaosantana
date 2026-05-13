<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\CaixaDiaria;
use App\Models\Pedido;
use App\Models\Produto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PedidoController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:pedidos.ver')->only(['index', 'show', 'talao']);
        $this->middleware('permission:pedidos.criar')->only(['create', 'store']);
        $this->middleware('permission:pedidos.editar')->only(['edit', 'update', 'fecharConta']);
        $this->middleware('permission:pedidos.apagar')->only('destroy');
        $this->middleware('permission:pedidos.gerir-estado')->only('atualizarEstado');
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Pedidos/Index', [
            'pedidos' => Pedido::with('mesa', 'user')
                ->when($request->estado, fn ($query, $estado) => $query->where('estado', $estado))
                ->when($request->mesa, fn ($query, $mesa) => $query->whereHas('mesa', fn ($q) => $q->where('numero', $mesa)))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => $request->only('estado', 'mesa'),
            'mesas' => $this->mesasParaPedido(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Pedidos/Show', [
            'mesas' => $this->mesasParaPedido(),
            'produtos' => Produto::with('categoria')->disponiveis()->orderBy('nome')->get(),
            'paraLevar' => request()->boolean('para_levar'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tipo_atendimento' => ['nullable', 'in:mesa,para_levar'],
            'mesa_id' => ['nullable', 'required_if:tipo_atendimento,mesa', 'exists:mesas,id'],
            'lugares_ocupados' => ['nullable', 'integer', 'min:1'],
            'observacoes' => ['nullable', 'string'],
        ]);

        if (! $this->caixaRestauranteAberta()) {
            return back()->withErrors(['mesa_id' => 'Abre a caixa do Restaurante antes de abrir pedidos.']);
        }

        $paraLevar = ($data['tipo_atendimento'] ?? 'mesa') === 'para_levar';
        $mesaPedido = null;

        if (! $paraLevar) {
            $mesa = Mesa::with('submesas')->findOrFail($data['mesa_id']);
            $mesaPedido = $this->mesaParaPedido($mesa, $data['lugares_ocupados'] ?? null);
        }

        $pedido = Pedido::create([
            'mesa_id' => $mesaPedido?->id,
            'user_id' => $request->user()->id,
            'tipo' => 'restaurante',
            'estado' => 'pendente',
            'observacoes' => $paraLevar
                ? trim('PARA LEVAR'.(($data['observacoes'] ?? null) ? ' - '.$data['observacoes'] : ''))
                : ($data['observacoes'] ?? null),
        ]);

        if ($mesaPedido) {
            $mesaPedido->update(['estado' => 'ocupada']);
            $mesaPedido->mesaPrincipal?->update(['estado' => 'ocupada']);
        }

        return to_route('pedidos.show', $pedido);
    }

    public function show(Pedido $pedido): Response
    {
        return Inertia::render('Pedidos/Show', [
            'pedido' => $pedido->load('mesa', 'user', 'items.produto.categoria'),
            'mesas' => $this->mesasParaPedido(),
            'produtos' => Produto::with('categoria')->disponiveis()->orderBy('nome')->get(),
        ]);
    }

    public function edit(Pedido $pedido): Response
    {
        return $this->show($pedido);
    }

    public function talao(Pedido $pedido): Response
    {
        return Inertia::render('Pedidos/Talao', [
            'pedido' => $pedido->load('mesa', 'user', 'items.produto.categoria'),
        ]);
    }

    public function update(Request $request, Pedido $pedido): RedirectResponse
    {
        $pedido->update($request->validate(['observacoes' => ['nullable', 'string']]));

        return back()->with('success', 'Pedido atualizado.');
    }

    public function destroy(Pedido $pedido): RedirectResponse
    {
        $pedido->delete();

        return to_route('pedidos.index')->with('success', 'Pedido apagado.');
    }

    public function atualizarEstado(Request $request, Pedido $pedido): RedirectResponse
    {
        $pedido->update($request->validate(['estado' => ['required', 'in:pendente,preparacao,pronto,entregue,cancelado']]));
        if (in_array($pedido->estado, ['entregue', 'cancelado'], true)) {
            $this->libertarMesaDoPedido($pedido);
        }

        return back()->with('success', 'Estado atualizado.');
    }

    public function fecharConta(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate([
            'metodo_pagamento' => ['nullable', 'in:dinheiro,mbway,multibanco,transferencia'],
            'valor_recebido' => ['nullable', 'numeric', 'min:0'],
            'troco' => ['nullable', 'numeric', 'min:0'],
        ]);

        $total = round($pedido->fresh('items')->total_calculado, 2);
        $valorRecebido = round((float) ($data['valor_recebido'] ?? $total), 2);
        $troco = round((float) ($data['troco'] ?? 0), 2);
        $excedente = round($valorRecebido - $total, 2);

        if ($valorRecebido < $total) {
            return back()->withErrors(['valor_recebido' => 'O valor recebido não pode ser inferior ao total.']);
        }

        if ($troco > $excedente) {
            return back()->withErrors(['troco' => 'O troco não pode ser superior ao valor a devolver.']);
        }

        $pedido->update([
            'estado' => 'entregue',
            'total' => $total,
            'valor_recebido' => $valorRecebido,
            'troco' => $troco,
            'doacao' => max(0, round($excedente - $troco, 2)),
            'metodo_pagamento' => $data['metodo_pagamento'] ?? 'dinheiro',
        ]);
        $this->libertarMesaDoPedido($pedido);

        return to_route('pedidos.talao', $pedido)->with('success', 'Conta fechada.');
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
            'localizacao' => 'sala',
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

        $temPedidosAtivos = $this->temPedidosAtivosNaMesa($mesaPrincipal)
            || $mesaPrincipal->submesas()
                ->whereHas('pedidos', fn ($query) => $query->whereIn('estado', ['pendente', 'preparacao', 'pronto']))
                ->exists();

        if (! $temPedidosAtivos) {
            $this->normalizarMesa($mesaPrincipal);

            return;
        }

        $mesaPrincipal->update(['estado' => $temPedidosAtivos ? 'ocupada' : 'livre']);
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

    private function mesasParaPedido()
    {
        return Mesa::ativas()
            ->withCount('submesas')
            ->with('mesaPrincipal:id,numero,nome')
            ->get()
            ->filter(fn ($mesa) => $mesa->mesa_principal_id || $mesa->submesas_count === 0)
            ->sortBy(fn ($mesa) => sprintf(
                '%03d-%03d',
                $mesa->mesaPrincipal?->numero ?? $mesa->numero,
                $mesa->mesa_principal_id ? $mesa->numero : 0
            ))
            ->values();
    }

    private function caixaRestauranteAberta(): bool
    {
        return CaixaDiaria::whereDate('data', today())
            ->where('ponto', 'Restaurante')
            ->where('estado', 'aberta')
            ->exists();
    }
}
