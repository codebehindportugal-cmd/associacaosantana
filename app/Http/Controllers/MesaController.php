<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MesaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:mesas.ver')->only(['index', 'show', 'sala']);
        $this->middleware('permission:mesas.criar')->only(['create', 'store']);
        $this->middleware('permission:mesas.editar')->only(['edit', 'update', 'dividir', 'juntar', 'libertar', 'guardarMapa']);
        $this->middleware('permission:mesas.apagar')->only('destroy');
    }

    public function index(): Response
    {
        return Inertia::render('Mesas/Index', [
            'mesas' => $this->mesasParaMapa(),
        ]);
    }

    public function sala(): Response
    {
        return Inertia::render('Sala/Index', [
            'mesas' => $this->mesasParaMapa(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Mesas/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        Mesa::create($this->validated($request));

        return to_route('mesas.index')->with('success', 'Mesa criada.');
    }

    public function show(Mesa $mesa): Response
    {
        return Inertia::render('Mesas/Form', ['mesa' => $mesa]);
    }

    public function edit(Mesa $mesa): Response
    {
        return Inertia::render('Mesas/Form', ['mesa' => $mesa]);
    }

    public function update(Request $request, Mesa $mesa): RedirectResponse
    {
        $mesa->update($this->validated($request, $mesa));

        return to_route('mesas.index')->with('success', 'Mesa atualizada.');
    }

    public function dividir(Request $request, Mesa $mesa): RedirectResponse
    {
        $data = $request->validate([
            'capacidade_submesa' => ['nullable', 'integer', 'between:1,10'],
            'partes' => ['nullable', 'integer', 'between:2,10'],
        ]);

        if ($this->temPedidosAtivos($mesa)) {
            return back()->with('error', 'Não é possível alterar a divisão enquanto existirem pedidos ativos.');
        }

        $mesa->submesas()->delete();

        $capacidadeMesa = max(1, (int) $mesa->capacidade);
        $capacidadeSubmesa = (int) ($data['capacidade_submesa'] ?? ceil($capacidadeMesa / (int) ($data['partes'] ?? 2)));
        $quantidadeSubmesas = (int) ceil($capacidadeMesa / $capacidadeSubmesa);
        $inicio = 1;

        foreach (range(1, $quantidadeSubmesas) as $parte) {
            $fim = min($capacidadeMesa, $inicio + $capacidadeSubmesa - 1);

            $mesa->submesas()->create([
                'numero' => ($mesa->numero * 100) + $parte,
                'nome' => 'Mesa '.$mesa->numero.chr(64 + $parte),
                'capacidade' => $fim - $inicio + 1,
                'lugares_inicio' => $inicio,
                'lugares_fim' => $fim,
                'localizacao' => 'sala',
                'estado' => 'livre',
                'ativa' => true,
            ]);

            $inicio = $fim + 1;
        }

        return back()->with('success', 'Mesa dividida em submesas de '.$capacidadeSubmesa.' lugares.');
    }

    public function juntar(Mesa $mesa): RedirectResponse
    {
        if ($this->temPedidosAtivos($mesa)) {
            return back()->with('error', 'Não é possível juntar a mesa enquanto existirem pedidos ativos.');
        }

        $mesa->submesas()->delete();
        $mesa->update(['estado' => 'livre']);

        return back()->with('success', 'Submesas removidas.');
    }

    public function libertar(Mesa $mesa): RedirectResponse
    {
        $mesaPrincipal = $mesa->mesaPrincipal ?: $mesa;

        if ($this->temPedidosAtivos($mesaPrincipal)) {
            return back()->with('error', 'Não é possível normalizar a mesa enquanto existirem pedidos ativos.');
        }

        $this->normalizarMesa($mesaPrincipal);

        return back()->with('success', 'Mesa libertada e reposta ao normal.');
    }

    public function guardarMapa(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mesas' => ['required', 'array'],
            'mesas.*.id' => ['required', 'exists:mesas,id'],
            'mesas.*.mapa_x' => ['required', 'integer', 'between:0,100'],
            'mesas.*.mapa_y' => ['required', 'integer', 'between:0,100'],
            'mesas.*.mapa_largura' => ['required', 'integer', 'between:4,40'],
            'mesas.*.mapa_altura' => ['required', 'integer', 'between:4,40'],
        ]);

        foreach ($data['mesas'] as $mesa) {
            Mesa::whereKey($mesa['id'])->whereNull('mesa_principal_id')->update([
                'mapa_x' => $mesa['mapa_x'],
                'mapa_y' => $mesa['mapa_y'],
                'mapa_largura' => $mesa['mapa_largura'],
                'mapa_altura' => $mesa['mapa_altura'],
            ]);
        }

        return back()->with('success', 'Mapa da sala guardado.');
    }

    public function destroy(Mesa $mesa): RedirectResponse
    {
        if ($this->temPedidosAtivos($mesa)) {
            return back()->with('error', 'Nao e possivel apagar a mesa enquanto existirem pedidos ativos.');
        }

        $mesa->delete();

        return to_route('mesas.index')->with('success', 'Mesa apagada.');
    }

    private function validated(Request $request, ?Mesa $mesa = null): array
    {
        return $request->validate([
            'numero' => ['required', 'integer', 'unique:mesas,numero,'.($mesa?->id ?? 'NULL')],
            'nome' => ['nullable', 'string', 'max:255'],
            'capacidade' => ['required', 'integer', 'between:1,10'],
            'localizacao' => ['required', 'in:sala,interior,exterior,bar'],
            'estado' => ['required', 'in:livre,ocupada,reservada'],
        ]);
    }

    private function temPedidosAtivos(Mesa $mesa): bool
    {
        $ativos = fn ($query) => $query->whereIn('estado', ['pendente', 'preparacao', 'pronto']);
        $ativosGrupo = fn ($query) => $query->whereIn('pedidos.estado', ['pendente', 'preparacao', 'pronto']);

        return $mesa->pedidos()->where($ativos)->exists()
            || $mesa->pedidosGrupo()->where($ativosGrupo)->exists()
            || $mesa->submesas()->whereHas('pedidos', $ativos)->exists()
            || $mesa->submesas()->whereHas('pedidosGrupo', $ativosGrupo)->exists();
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
