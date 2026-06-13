<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\CaixaDiaria;
use App\Models\Pedido;
use App\Models\Produto;
use App\Services\PrintJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $estado = $request->estado ?? 'abertos';
        $pedidosBase = Pedido::where('tipo', 'restaurante');

        return Inertia::render('Pedidos/Index', [
            'pedidos' => (clone $pedidosBase)
                ->with('mesa', 'user', 'pos', 'items')
                ->when($estado === 'abertos', fn ($query) => $query->whereIn('estado', ['pendente', 'preparacao', 'pronto']))
                ->when($estado !== 'abertos' && $estado !== 'todos', fn ($query) => $query->where('estado', $estado))
                ->when($request->mesa, fn ($query, $mesa) => $query->whereHas('mesa', fn ($q) => $q->where('numero', $mesa)))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => [
                'estado' => $estado,
                'mesa' => $request->mesa,
            ],
            'resumo' => [
                'abertos' => (clone $pedidosBase)->whereIn('estado', ['pendente', 'preparacao', 'pronto'])->count(),
                'pronto' => (clone $pedidosBase)->where('estado', 'pronto')->count(),
                'fechados_hoje' => (clone $pedidosBase)->where('estado', 'entregue')->whereDate('updated_at', today())->count(),
            ],
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
        if ($request->input('tipo_atendimento') === 'para_levar') {
            $request->merge([
                'mesa_id' => null,
                'lugares_ocupados' => null,
                'submesa_letra' => null,
            ]);
        }

        $data = $request->validate([
            'tipo_atendimento' => ['nullable', 'in:mesa,para_levar'],
            'mesa_id' => ['nullable', 'required_if:tipo_atendimento,mesa', 'exists:mesas,id'],
            'lugares_ocupados' => ['nullable', 'integer', 'min:1'],
            'submesa_letra' => ['nullable', 'string', 'regex:/^[A-Za-z]$/'],
            'observacoes' => ['nullable', 'string'],
        ]);

        if (! $this->caixaRestauranteAberta()) {
            return back()->withErrors(['mesa_id' => 'Abre a caixa do Restaurante antes de abrir pedidos.']);
        }

        $paraLevar = ($data['tipo_atendimento'] ?? 'mesa') === 'para_levar';
        $mesaPedido = null;
        $mesasGrupo = collect();

        if (! $paraLevar) {
            $mesa = Mesa::with('submesas')->findOrFail($data['mesa_id']);
            $lugares = (int) ($data['lugares_ocupados'] ?? 0);

            if ($lugares > $this->capacidadeFisicaMesa($mesa)) {
                $mesasGrupo = $this->mesasLivresParaGrupo($mesa, $lugares);

                if ($mesasGrupo->sum('capacidade') < $lugares) {
                    return back()->withErrors(['lugares_ocupados' => 'Nao existem mesas livres suficientes para este grupo.']);
                }

                $mesaPedido = $mesa;
            } else {
                $mesaPedido = $this->mesaParaPedido($mesa, $data['lugares_ocupados'] ?? null, $data['submesa_letra'] ?? null);
            }
        }

        $pedido = Pedido::create([
            'mesa_id' => $mesaPedido?->id,
            'user_id' => $request->user()->id,
            'operador_nome' => $request->user()->name,
            'tipo' => 'restaurante',
            'estado' => 'pendente',
            'observacoes' => $paraLevar
                ? trim('PARA LEVAR'.(($data['observacoes'] ?? null) ? ' - '.$data['observacoes'] : ''))
                : ($data['observacoes'] ?? null),
        ]);

        if ($mesaPedido) {
            $mesaPedido->update(['estado' => 'ocupada']);
            $mesaPedido->mesaPrincipal?->update(['estado' => 'ocupada']);

            if ($mesasGrupo->isNotEmpty()) {
                $pedido->mesasGrupo()->sync($mesasGrupo->pluck('id'));
                Mesa::whereIn('id', $mesasGrupo->pluck('id'))->update(['estado' => 'ocupada']);
            }
        }

        return to_route('pedidos.show', [
            'pedido' => $pedido,
            'caixa' => $paraLevar ? 1 : null,
        ]);
    }

    public function show(Pedido $pedido): Response
    {
        return Inertia::render('Pedidos/Show', [
            'pedido' => $pedido->load('mesa', 'user', 'pos', 'items.produto.categoria'),
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
            'pedido' => $pedido->load('mesa', 'user', 'pos', 'items.produto.categoria'),
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
        $pedido->update($request->validate(['estado' => ['required', 'in:pendente,preparacao,entregue,cancelado']]));
        if (in_array($pedido->estado, ['entregue', 'cancelado'], true)) {
            $this->libertarMesaDoPedido($pedido);
        }

        return back()->with('success', 'Estado atualizado.');
    }

    public function fecharConta(Request $request, Pedido $pedido, PrintJobService $printJobs): RedirectResponse
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
        $printJobs->criarConta($pedido->fresh('mesa.mesaPrincipal', 'user', 'pos', 'items.produto.categoria'));

        return to_route('pedidos.talao', $pedido)->with('success', 'Conta fechada.');
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

    private function proximaLetraSubmesa(Mesa $mesaPrincipal): string
    {
        $usadas = $mesaPrincipal->submesas()
            ->pluck('nome')
            ->map(fn ($nome) => preg_replace('/^Mesa\s*'.preg_quote((string) $mesaPrincipal->numero, '/').'/i', '', (string) $nome))
            ->map(fn ($letra) => strtoupper(trim((string) $letra)))
            ->filter()
            ->values()
            ->all();

        foreach (range('A', 'Z') as $letra) {
            if (! in_array($letra, $usadas, true)) {
                return $letra;
            }
        }

        return (string) ($mesaPrincipal->submesas()->count() + 1);
    }

    private function normalizarLetraSubmesa(?string $letra): ?string
    {
        $letra = strtoupper(trim((string) $letra));

        return preg_match('/^[A-Z]$/', $letra) ? $letra : null;
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

        $mesaPrincipal->update(['estado' => 'ocupada']);
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

    private function temPedidosAtivosNaMesaCompleta(Mesa $mesa): bool
    {
        return $this->temPedidosAtivosNaMesa($mesa)
            || $this->temPedidosAtivosNasSubmesas($mesa);
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

    private function mesaDisponivelParaGrupo(Mesa $mesa): bool
    {
        return ! $mesa->mesa_principal_id
            && $mesa->estado === 'livre'
            && ! $this->temPedidosAtivosNaMesa($mesa)
            && ! $this->temPedidosAtivosNasSubmesas($mesa)
            && ! $mesa->pedidosGrupo()->whereIn('pedidos.estado', ['pendente', 'preparacao', 'pronto'])->exists();
    }

    private function capacidadeFisicaMesa(Mesa $mesa): int
    {
        return min(10, max(1, (int) $mesa->capacidade));
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
        return CaixaDiaria::abertaParaPonto('Restaurante') !== null;
    }
}
