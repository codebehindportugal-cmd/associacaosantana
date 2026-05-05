<?php

namespace App\Http\Controllers;

use App\Models\CaixaDiaria;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CaixaDiariaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:pedidos.ver')->only('index');
        $this->middleware('permission:pedidos.criar')->only('store');
        $this->middleware('permission:pedidos.editar')->only('fechar');
    }

    public function index(): Response
    {
        $caixas = CaixaDiaria::with('user', 'fechadoPor')
            ->whereDate('data', today())
            ->orderBy('ponto')
            ->get();

        $vendasBar = Pedido::whereIn('tipo', ['bar_conta', 'bar_prepago'])
            ->whereDate('created_at', today())
            ->where(fn ($query) => $query->where('estado', 'entregue')->orWhere('pago_antecipado', true))
            ->select('ponto_bar', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as pedidos'))
            ->groupBy('ponto_bar')
            ->get()
            ->keyBy(fn ($linha) => $linha->ponto_bar ?: 'Sem ponto definido');

        $vendasRestaurante = Pedido::where('tipo', 'restaurante')
            ->whereDate('created_at', today())
            ->where('estado', 'entregue')
            ->select(DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as pedidos'))
            ->first();

        $vendas = $vendasBar;
        $vendas->put('Restaurante', (object) [
            'total' => $vendasRestaurante?->total ?? 0,
            'pedidos' => $vendasRestaurante?->pedidos ?? 0,
        ]);

        return Inertia::render('Caixa/Index', [
            'data' => today()->toDateString(),
            'pontos_padrao' => $this->pontosPadrao(),
            'caixas' => $caixas->map(function (CaixaDiaria $caixa) use ($vendas) {
                $venda = $vendas->get($caixa->ponto);
                $esperado = (float) $caixa->fundo_maneio + (float) ($venda->total ?? 0);

                return [
                    'id' => $caixa->id,
                    'ponto' => $caixa->ponto,
                    'fundo_maneio' => (float) $caixa->fundo_maneio,
                    'estado' => $caixa->estado,
                    'vendas' => (float) ($venda->total ?? 0),
                    'pedidos' => (int) ($venda->pedidos ?? 0),
                    'esperado_caixa' => $esperado,
                    'valor_contado' => $caixa->valor_contado !== null ? (float) $caixa->valor_contado : null,
                    'diferenca' => (float) $caixa->diferenca,
                    'observacoes_fecho' => $caixa->observacoes_fecho,
                    'aberto_por' => $caixa->user?->name,
                    'aberto_as' => $caixa->created_at,
                    'fechado_por' => $caixa->fechadoPor?->name,
                    'fechado_as' => $caixa->fechado_at,
                ];
            })->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ponto' => ['required', 'string', 'max:80'],
            'fundo_maneio' => ['required', 'numeric', 'min:0'],
        ]);

        CaixaDiaria::updateOrCreate(
            ['data' => today()->toDateString(), 'ponto' => $data['ponto']],
            [
                'fundo_maneio' => round((float) $data['fundo_maneio'], 2),
                'estado' => 'aberta',
                'valor_contado' => null,
                'diferenca' => 0,
                'observacoes_fecho' => null,
                'user_id' => $request->user()->id,
                'fechado_user_id' => null,
                'fechado_at' => null,
            ]
        );

        return back()->with('success', 'Caixa aberta para '.$data['ponto'].'.');
    }

    public function fechar(Request $request, CaixaDiaria $caixa): RedirectResponse
    {
        abort_unless($caixa->data->isSameDay(today()), 404);

        $data = $request->validate([
            'valor_contado' => ['required', 'numeric', 'min:0'],
            'observacoes_fecho' => ['nullable', 'string', 'max:1000'],
        ]);

        $vendas = $this->vendasDoPonto($caixa->ponto);
        $esperado = round((float) $caixa->fundo_maneio + $vendas, 2);
        $valorContado = round((float) $data['valor_contado'], 2);

        $caixa->update([
            'estado' => 'fechada',
            'valor_contado' => $valorContado,
            'diferenca' => round($valorContado - $esperado, 2),
            'observacoes_fecho' => $data['observacoes_fecho'] ?? null,
            'fechado_user_id' => $request->user()->id,
            'fechado_at' => now(),
        ]);

        return back()->with('success', 'Caixa fechada para '.$caixa->ponto.'.');
    }

    private function vendasDoPonto(string $ponto): float
    {
        if ($ponto === 'Restaurante') {
            return (float) Pedido::where('tipo', 'restaurante')
                ->whereDate('created_at', today())
                ->where('estado', 'entregue')
                ->sum('total');
        }

        return (float) Pedido::whereIn('tipo', ['bar_conta', 'bar_prepago'])
            ->whereDate('created_at', today())
            ->where('ponto_bar', $ponto)
            ->where(fn ($query) => $query->where('estado', 'entregue')->orWhere('pago_antecipado', true))
            ->sum('total');
    }

    private function pontosPadrao(): array
    {
        return ['Restaurante', 'Cafe', 'Bar 1', 'Bar 2'];
    }
}
