<?php

namespace App\Http\Controllers;

use App\Models\CaixaDiaria;
use App\Models\CaucaoDevolucao;
use App\Models\Configuracao;
use App\Models\Pedido;
use App\Models\PosSession;
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
                $caucao = $this->caucoesDoPonto($caixa);
                $esperado = (float) $caixa->fundo_maneio + (float) ($venda->total ?? 0) + $caucao['saldo'];

                return [
                    'id' => $caixa->id,
                    'data' => $caixa->data->toDateString(),
                    'ponto' => $caixa->ponto,
                    'fundo_maneio' => (float) $caixa->fundo_maneio,
                    'estado' => $caixa->estado,
                    'vendas' => (float) ($venda->total ?? 0),
                    'pedidos' => (int) ($venda->pedidos ?? 0),
                    'esperado_caixa' => round($esperado, 2),
                    'caucao' => $caucao,
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

        // Decidido antes de guardar, para nao contar a caixa que esta a ser aberta
        $reporSenhas = $this->primeiraCaixaDeBar($data['ponto']);

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

        if ($reporSenhas) {
            $this->reporSenhasBar();
        }

        return back()->with('success', 'Caixa aberta para '.$data['ponto'].'.'
            .($reporSenhas ? ' As senhas recomecam no 1.' : ''));
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
        $esperado = round((float) $caixa->fundo_maneio + (float) $vendas + $this->caucoesDoPonto($caixa)['saldo'], 2);
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

    /**
     * A contagem das senhas e partilhada por todos os pontos de bar, por isso
     * so recomeca quando se abre o primeiro ponto — abrir o segundo a meio da
     * noite nao pode repetir numeros que ja andam na mao dos clientes.
     * O Restaurante trabalha por mesas e nunca mexe nas senhas.
     */
    private function primeiraCaixaDeBar(string $ponto): bool
    {
        if ($ponto === 'Restaurante') {
            return false;
        }

        return ! CaixaDiaria::where('estado', 'aberta')
            ->where('ponto', '!=', 'Restaurante')
            ->exists();
    }

    private function reporSenhasBar(): void
    {
        Configuracao::updateOrCreate(
            ['chave' => 'ultima_senha_bar'],
            ['valor' => '0', 'descricao' => 'Ultima senha emitida no bar (reposta ao abrir a primeira caixa)']
        );
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

    /**
     * Caucoes dos metros neste ponto. Nao sao vendas: entram e saem da gaveta.
     * recebidas  = caucao cobrada nas senhas
     * bebidas    = metros devolvidos descontados numa senha (dinheiro que nao entrou)
     * dinheiro   = metros devolvidos com o dinheiro devolvido (saiu da gaveta)
     * saldo      = efeito na gaveta (um metro pode ser comprado num ponto e devolvido noutro)
     */
    private function caucoesDoPonto(CaixaDiaria $caixa): array
    {
        $vazio = ['recebidas' => 0.0, 'bebidas' => 0.0, 'dinheiro' => 0.0, 'saldo' => 0.0];

        if ($caixa->ponto === 'Restaurante') {
            return $vazio;
        }

        $recebidas = (float) Pedido::whereIn('tipo', ['bar_conta', 'bar_prepago'])
            ->where('created_at', '>=', $caixa->created_at)
            ->when($caixa->fechado_at, fn ($query) => $query->where('created_at', '<=', $caixa->fechado_at))
            ->where('ponto_bar', $caixa->ponto)
            ->where(fn ($query) => $query->where('estado', 'entregue')->orWhere('pago_antecipado', true))
            ->sum('caucao_cobrada');

        $devolucoes = CaucaoDevolucao::where('ponto', $caixa->ponto)
            ->where('created_at', '>=', $caixa->created_at)
            ->when($caixa->fechado_at, fn ($query) => $query->where('created_at', '<=', $caixa->fechado_at))
            ->selectRaw('modo, SUM(valor_total) as total')
            ->groupBy('modo')
            ->pluck('total', 'modo');

        $bebidas = (float) ($devolucoes['bebidas'] ?? 0);
        $dinheiro = (float) ($devolucoes['dinheiro'] ?? 0);

        return [
            'recebidas' => round($recebidas, 2),
            'bebidas' => round($bebidas, 2),
            'dinheiro' => round($dinheiro, 2),
            'saldo' => round($recebidas - $bebidas - $dinheiro, 2),
        ];
    }

    private function pontosPadrao(): array
    {
        // Os pontos do bar vêm dos postos POS ativos, para cada evento ter os seus
        return ['Restaurante', ...PosSession::pontosBar()];
    }
}
