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
        $this->middleware('permission:caixa.ver')->only('index');
        $this->middleware('permission:caixa.gerir')->only(['store', 'fechar']);
    }

    public function index(Request $request): Response
    {
        $caixas = CaixaDiaria::with('user', 'fechadoPor')
            ->where(function ($query) {
                $query->whereDate('data', today())
                    ->orWhere('estado', 'aberta');
            })
            ->when(! $request->user()->can('bar.ver'), fn ($query) => $query->where('ponto', 'Restaurante'))
            ->orderBy('ponto')
            ->orderByDesc('data')
            ->get();

        return Inertia::render('Caixa/Index', [
            'data' => today()->toDateString(),
            'pontos_padrao' => $request->user()->can('bar.ver') ? $this->pontosPadrao() : ['Restaurante'],
            'caixas' => $caixas->map(function (CaixaDiaria $caixa) {
                $venda = $this->vendasDoPonto($caixa);
                $esperado = (float) $caixa->fundo_maneio + (float) ($venda->total ?? 0);

                return [
                    'id' => $caixa->id,
                    'data' => $caixa->data->toDateString(),
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

        abort_if($data['ponto'] !== 'Restaurante' && ! $request->user()->can('bar.ver'), 403);

        $caixa = CaixaDiaria::abertaParaPonto($data['ponto']);

        if (! $caixa) {
            $caixa = CaixaDiaria::firstOrNew([
                'data' => today()->toDateString(),
                'ponto' => $data['ponto'],
            ]);
        }

        $caixa->fill([
            'fundo_maneio' => round((float) $data['fundo_maneio'], 2),
            'estado' => 'aberta',
            'valor_contado' => null,
            'diferenca' => 0,
            'observacoes_fecho' => null,
            'user_id' => $request->user()->id,
            'fechado_user_id' => null,
            'fechado_at' => null,
        ])->save();

        return back()->with('success', 'Caixa aberta para '.$data['ponto'].'.');
    }

    public function fechar(Request $request, CaixaDiaria $caixa): RedirectResponse
    {
        abort_unless($caixa->estado === 'aberta', 404);
        abort_if($caixa->ponto !== 'Restaurante' && ! $request->user()->can('bar.ver'), 403);

        $data = $request->validate([
            'valor_contado' => ['required', 'numeric', 'min:0'],
            'observacoes_fecho' => ['nullable', 'string', 'max:1000'],
        ]);

        $vendas = $this->vendasDoPonto($caixa)?->total ?? 0;
        $esperado = round((float) $caixa->fundo_maneio + (float) $vendas, 2);
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

    private function vendasDoPonto(CaixaDiaria $caixa): object
    {
        if ($caixa->ponto === 'Restaurante') {
            return Pedido::where('tipo', 'restaurante')
                ->where('created_at', '>=', $caixa->created_at)
                ->when($caixa->fechado_at, fn ($query) => $query->where('created_at', '<=', $caixa->fechado_at))
                ->where('estado', 'entregue')
                ->select(DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as pedidos'))
                ->first();
        }

        return Pedido::whereIn('tipo', ['bar_conta', 'bar_prepago'])
            ->where('created_at', '>=', $caixa->created_at)
            ->when($caixa->fechado_at, fn ($query) => $query->where('created_at', '<=', $caixa->fechado_at))
            ->where('ponto_bar', $caixa->ponto)
            ->where(fn ($query) => $query->where('estado', 'entregue')->orWhere('pago_antecipado', true))
            ->select(DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as pedidos'))
            ->first();
    }

    private function pontosPadrao(): array
    {
        return ['Restaurante', 'Cafe', 'Bar 1', 'Bar 2'];
    }
}
