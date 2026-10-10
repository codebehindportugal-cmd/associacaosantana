<?php

namespace App\Http\Controllers;

use App\Models\CaixaDiaria;
use App\Models\CaucaoDevolucao;
use App\Models\Configuracao;
use App\Models\Pedido;
use App\Models\PosSession;
use App\Models\TalaoConfig;
use App\Services\CaixaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CaixaDiariaController extends Controller
{
    public function __construct(private CaixaService $caixas)
    {
        $this->middleware('permission:caixa.ver')->only(['index', 'talao', 'imprimir']);
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
            'caixas' => $caixas->map(fn (CaixaDiaria $caixa) => [
                'id' => $caixa->id,
                'data' => $caixa->data->toDateString(),
                'ponto' => $caixa->ponto,
                'fundo_maneio' => (float) $caixa->fundo_maneio,
                'estado' => $caixa->estado,
                ...$this->caixas->resumo($caixa),
                'valor_contado' => $caixa->valor_contado !== null ? (float) $caixa->valor_contado : null,
                'diferenca' => (float) $caixa->diferenca,
                'observacoes_fecho' => $caixa->observacoes_fecho,
                'aberto_por' => $caixa->user?->name,
                'aberto_as' => $caixa->created_at,
                'fechado_por' => $caixa->fechadoPor?->name ?? $caixa->fechado_por_nome,
                'fechado_as' => $caixa->fechado_at,
            ])->values(),
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
            // Opcional: quantas notas/moedas de cada valor
            'contagem' => ['nullable', 'array'],
            'contagem.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $caixa = $this->caixas->fechar($caixa, (float) $data['valor_contado'], $data['contagem'] ?? [], $data['observacoes_fecho'] ?? null, $request->user()->id);

        return $this->enviarTalao($caixa, 'Caixa fechada para '.$caixa->ponto.'.');
    }

    /** Talao da caixa para imprimir no browser (80 mm). Caixa aberta = leitura sem fechar. */
    public function talao(Request $request, CaixaDiaria $caixa): Response
    {
        $this->autorizarPonto($request, $caixa);

        return Inertia::render('Caixa/Talao', [
            'payload' => $this->caixas->payloadTalao($caixa),
            'modo' => $this->caixas->impressoraDoPonto($caixa)?->tipo ?? 'navegador',
            'voltar' => route('caixa.index'),
        ]);
    }

    /** Reimprime o fecho (ou tira uma leitura de uma caixa aberta). */
    public function imprimir(Request $request, CaixaDiaria $caixa): RedirectResponse
    {
        $this->autorizarPonto($request, $caixa);

        return $this->enviarTalao($caixa, $caixa->estado === 'fechada' ? 'Talão do fecho enviado.' : 'Leitura da caixa enviada.');
    }

    private function autorizarPonto(Request $request, CaixaDiaria $caixa): void
    {
        abort_if($caixa->ponto !== 'Restaurante' && ! $request->user()->can('bar.ver'), 403);
    }

    /** Horas sem senhas a partir das quais a contagem pode recomecar no 1. */
    private const HORAS_PARA_REPOR_SENHAS = 6;

    /**
     * A contagem das senhas e partilhada por todos os pontos de bar, por isso
     * so recomeca quando se abre o primeiro ponto — abrir o segundo a meio da
     * noite nao pode repetir numeros que ja andam na mao dos clientes.
     *
     * Tambem nao recomeca se houve senhas nas ultimas horas: fechar as caixas
     * todas para contar (troca de turno) e voltar a abrir continua a contagem.
     * O Restaurante trabalha por mesas e nunca mexe nas senhas.
     */
    private function primeiraCaixaDeBar(string $ponto): bool
    {
        if ($ponto === 'Restaurante') {
            return false;
        }

        $outraAberta = CaixaDiaria::where('estado', 'aberta')
            ->where('ponto', '!=', 'Restaurante')
            ->exists();

        $senhasRecentes = Pedido::whereIn('tipo', ['bar_conta', 'bar_prepago'])
            ->whereNotNull('numero_senha')
            ->where('created_at', '>=', now()->subHours(self::HORAS_PARA_REPOR_SENHAS))
            ->exists();

        return ! $outraAberta && ! $senhasRecentes;
    }

    private function reporSenhasBar(): void
    {
        Configuracao::updateOrCreate(
            ['chave' => 'ultima_senha_bar'],
            ['valor' => '0', 'descricao' => 'Ultima senha emitida no bar (reposta ao abrir a primeira caixa)']
        );
    }

    /** Pelo agente sai sozinho; senao abre-se a pagina do talao para imprimir no browser. */
    private function enviarTalao(CaixaDiaria $caixa, string $mensagem): RedirectResponse
    {
        $job = $this->caixas->imprimirTalao($caixa);

        if ($job) {
            return back()->with('success', $mensagem.' O talão saiu na impressora '.$job->impressora?->nome.'.');
        }

        return back()->with('success', $mensagem)->with('talao_caixa', route('caixa.talao', $caixa));
    }

    private function pontosPadrao(): array
    {
        // Os pontos do bar vêm dos postos POS ativos, para cada evento ter os seus
        return ['Restaurante', ...PosSession::pontosBar()];
    }
}
