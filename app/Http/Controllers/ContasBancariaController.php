<?php

namespace App\Http\Controllers;

use App\Models\FaturaCompra;
use App\Models\FestaMovimento;
use App\Models\MovimentoFinanceiro;
use App\Models\Pedido;
use App\Models\SaldoConta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContasBancariaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:relatorios.ver')->only('index');
        $this->middleware('permission:relatorios.ver')->only(['store', 'update', 'destroy', 'atualizarSaldo']);
    }

    public function index(Request $request): Response
    {
        $inicio = $request->date('data_inicio') ?? now()->startOfYear();
        $fim    = $request->date('data_fim')    ?? now()->endOfYear();
        $conta  = $request->input('conta');
        $tipo   = $request->input('tipo');

        // Saldos calculados acumulados de todos os tempos
        $saldoBancoCalculado = (float) MovimentoFinanceiro::where('conta', 'banco')
            ->selectRaw("SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END) AS saldo")
            ->value('saldo');

        $saldoPrazoCalculado = (float) MovimentoFinanceiro::where('conta', 'prazo')
            ->selectRaw("SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END) AS saldo")
            ->value('saldo');

        // Saldos confirmados (introduzidos manualmente do extrato bancário)
        $saldosConfirmados = SaldoConta::all()->keyBy('conta');
        $saldoBancoConf = $saldosConfirmados->get('banco');
        $saldoPrazoConf = $saldosConfirmados->get('prazo');

        // Totais do período filtrado
        $query = MovimentoFinanceiro::query()
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()]);

        if ($conta) {
            $query->where('conta', $conta);
        }

        if ($tipo) {
            $query->where('tipo', $tipo);
        }

        $movimentos = (clone $query)
            ->with('user:id,name')
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        $totalEntradas = (float) (clone $query)->where('tipo', 'entrada')->sum('valor');
        $totalSaidas   = (float) (clone $query)->where('tipo', 'saida')->sum('valor');

        // Lucros das festas no ano corrente
        $anoInicio = now()->startOfYear()->toDateString();
        $anoFim    = now()->endOfYear()->toDateString();

        $pedidosFesta = Pedido::query()
            ->whereBetween(DB::raw('DATE(created_at)'), [$anoInicio, $anoFim])
            ->where(fn ($q) => $q->where('estado', 'entregue')->orWhere('pago_antecipado', true))
            ->get(['total', 'doacao']);

        $receitasFesta = (float) $pedidosFesta->sum('total') + (float) $pedidosFesta->sum('doacao');
        $receitasFestaManual = (float) FestaMovimento::where('tipo', 'receita')
            ->whereBetween('data', [$anoInicio, $anoFim])
            ->sum('valor');

        $custosCompras = (float) FaturaCompra::whereBetween('data', [$anoInicio, $anoFim])->sum('total');
        $custosFestaManual = (float) FestaMovimento::where('tipo', 'custo')
            ->whereBetween('data', [$anoInicio, $anoFim])
            ->sum('valor');

        $totalReceitasFesta = $receitasFesta + $receitasFestaManual;
        $totalCustosFesta   = $custosCompras + $custosFestaManual;
        $lucroFesta         = $totalReceitasFesta - $totalCustosFesta;

        return Inertia::render('Contas/Index', [
            'filters' => [
                'data_inicio' => $inicio->toDateString(),
                'data_fim'    => $fim->toDateString(),
                'conta'       => $conta,
                'tipo'        => $tipo,
            ],
            'movimentos' => $movimentos,
            'resumo' => [
                'saldo_banco_calculado' => $saldoBancoCalculado,
                'saldo_prazo_calculado' => $saldoPrazoCalculado,
                'saldo_total_calculado' => $saldoBancoCalculado + $saldoPrazoCalculado,
                'saldo_banco_confirmado' => $saldoBancoConf ? [
                    'valor' => $saldoBancoConf->valor,
                    'data'  => $saldoBancoConf->data->toDateString(),
                    'notas' => $saldoBancoConf->notas,
                ] : null,
                'saldo_prazo_confirmado' => $saldoPrazoConf ? [
                    'valor' => $saldoPrazoConf->valor,
                    'data'  => $saldoPrazoConf->data->toDateString(),
                    'notas' => $saldoPrazoConf->notas,
                ] : null,
                'total_entradas' => $totalEntradas,
                'total_saidas'   => $totalSaidas,
                'resultado'      => $totalEntradas - $totalSaidas,
            ],
            'festaAno' => now()->year,
            'lucroFesta' => [
                'total_receitas' => $totalReceitasFesta,
                'total_custos'   => $totalCustosFesta,
                'lucro'          => $lucroFesta,
            ],
            'categoriasEntrada' => self::CATEGORIAS_ENTRADA,
            'categoriasSaida'   => self::CATEGORIAS_SAIDA,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = auth()->id();

        MovimentoFinanceiro::create($data);

        return back()->with('success', 'Movimento registado com sucesso.');
    }

    public function update(Request $request, MovimentoFinanceiro $contaBancaria): RedirectResponse
    {
        $contaBancaria->update($this->validated($request));

        return back()->with('success', 'Movimento atualizado.');
    }

    public function destroy(MovimentoFinanceiro $contaBancaria): RedirectResponse
    {
        $contaBancaria->delete();

        return back()->with('success', 'Movimento apagado.');
    }

    public function atualizarSaldo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'conta' => ['required', 'in:banco,prazo'],
            'valor' => ['required', 'numeric', 'min:0'],
            'data'  => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:255'],
        ]);

        SaldoConta::updateOrCreate(
            ['conta' => $data['conta']],
            array_merge($data, ['user_id' => auth()->id()])
        );

        return back()->with('success', 'Saldo atualizado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'tipo'       => ['required', 'in:entrada,saida'],
            'descricao'  => ['required', 'string', 'max:255'],
            'valor'      => ['required', 'numeric', 'min:0.01'],
            'data'       => ['required', 'date'],
            'categoria'  => ['nullable', 'string', 'max:100'],
            'conta'      => ['required', 'in:banco,prazo'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'notas'      => ['nullable', 'string'],
        ]);
    }

    const CATEGORIAS_ENTRADA = [
        ['valor' => 'renda_cafe',    'label' => 'Renda do café'],
        ['valor' => 'cotas',         'label' => 'Cotas de sócios'],
        ['valor' => 'aluguer_salao', 'label' => 'Aluguer do salão'],
        ['valor' => 'patrocinios',   'label' => 'Patrocínios'],
        ['valor' => 'lucro_festa',   'label' => 'Lucro da festa'],
        ['valor' => 'eventos',       'label' => 'Outros eventos'],
        ['valor' => 'subsidios',     'label' => 'Subsídios/Apoios'],
        ['valor' => 'donativos',     'label' => 'Donativos'],
        ['valor' => 'juros',         'label' => 'Juros bancários'],
        ['valor' => 'transferencia_interna', 'label' => 'Transferência interna'],
        ['valor' => 'outros',        'label' => 'Outros'],
    ];

    const CATEGORIAS_SAIDA = [
        ['valor' => 'electricidade', 'label' => 'Electricidade'],
        ['valor' => 'agua',          'label' => 'Água'],
        ['valor' => 'comunicacoes',  'label' => 'Internet/Telefone'],
        ['valor' => 'seguros',       'label' => 'Seguros'],
        ['valor' => 'limpeza',       'label' => 'Limpeza'],
        ['valor' => 'manutencao',    'label' => 'Manutenção/Obras'],
        ['valor' => 'licencas',      'label' => 'Licenças/Taxas'],
        ['valor' => 'contabilidade', 'label' => 'Contabilidade'],
        ['valor' => 'fornecedores',  'label' => 'Fornecedores'],
        ['valor' => 'salarios',      'label' => 'Salários/Funcionários'],
        ['valor' => 'eventos_despesas', 'label' => 'Despesas com eventos'],
        ['valor' => 'impostos',      'label' => 'Impostos/AT'],
        ['valor' => 'banco',         'label' => 'Comissões bancárias'],
        ['valor' => 'transferencia_interna', 'label' => 'Transferência interna'],
        ['valor' => 'outros',        'label' => 'Outros'],
    ];
}
