<?php

namespace App\Http\Controllers;

use App\Models\MovimentoFinanceiro;
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
        $this->middleware('permission:relatorios.ver')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): Response
    {
        $inicio = $request->date('data_inicio') ?? now()->startOfYear();
        $fim    = $request->date('data_fim')    ?? now()->endOfYear();
        $conta  = $request->input('conta');
        $tipo   = $request->input('tipo');

        // Saldos acumulados de todos os tempos (para cada conta)
        $saldoBanco = (float) MovimentoFinanceiro::where('conta', 'banco')
            ->selectRaw("SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END) AS saldo")
            ->value('saldo');

        $saldoPrazo = (float) MovimentoFinanceiro::where('conta', 'prazo')
            ->selectRaw("SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END) AS saldo")
            ->value('saldo');

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

        return Inertia::render('Contas/Index', [
            'filters' => [
                'data_inicio' => $inicio->toDateString(),
                'data_fim'    => $fim->toDateString(),
                'conta'       => $conta,
                'tipo'        => $tipo,
            ],
            'movimentos' => $movimentos,
            'resumo' => [
                'saldo_banco'     => $saldoBanco,
                'saldo_prazo'     => $saldoPrazo,
                'saldo_total'     => $saldoBanco + $saldoPrazo,
                'total_entradas'  => $totalEntradas,
                'total_saidas'    => $totalSaidas,
                'resultado'       => $totalEntradas - $totalSaidas,
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
        ['valor' => 'cotas',       'label' => 'Cotas de sócios'],
        ['valor' => 'alugueres',   'label' => 'Aluguer do salão'],
        ['valor' => 'patrocinios', 'label' => 'Patrocínios'],
        ['valor' => 'eventos',     'label' => 'Eventos'],
        ['valor' => 'subsidios',   'label' => 'Subsídios/Apoios'],
        ['valor' => 'donativos',   'label' => 'Donativos'],
        ['valor' => 'juros',       'label' => 'Juros bancários'],
        ['valor' => 'transferencia_interna', 'label' => 'Transferência interna'],
        ['valor' => 'outros',      'label' => 'Outros'],
    ];

    const CATEGORIAS_SAIDA = [
        ['valor' => 'fornecedores',  'label' => 'Fornecedores/Stock'],
        ['valor' => 'electricidade', 'label' => 'Electricidade'],
        ['valor' => 'agua',          'label' => 'Água'],
        ['valor' => 'comunicacoes',  'label' => 'Telecomunicações'],
        ['valor' => 'limpeza',       'label' => 'Limpeza'],
        ['valor' => 'manutencao',    'label' => 'Manutenção'],
        ['valor' => 'seguros',       'label' => 'Seguros'],
        ['valor' => 'licencas',      'label' => 'Licenças/Taxas'],
        ['valor' => 'salarios',      'label' => 'Salários'],
        ['valor' => 'eventos_despesas', 'label' => 'Despesas com eventos'],
        ['valor' => 'impostos',      'label' => 'Impostos'],
        ['valor' => 'banco',         'label' => 'Comissões bancárias'],
        ['valor' => 'transferencia_interna', 'label' => 'Transferência interna'],
        ['valor' => 'outros',        'label' => 'Outros'],
    ];
}
