<?php

namespace App\Http\Controllers;

use App\Models\AssociacaoParceira;
use App\Models\Configuracao;
use App\Models\FaturaCompra;
use App\Models\FestaMovimento;
use App\Support\ReceitaFesta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FestaContaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:relatorios.ver')->only('index');
        $this->middleware('permission:relatorios.ver')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): Response
    {
        $inicio = $request->date('data_inicio') ?? now()->startOfYear();
        $fim = $request->date('data_fim') ?? now()->endOfYear();

        $movimentos = FestaMovimento::query()
            ->where(function ($query) use ($inicio, $fim) {
                $query->whereNull('data')
                    ->orWhereBetween('data', [$inicio->toDateString(), $fim->toDateString()]);
            })
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        $custosAutomaticos = collect([
            [
                'categoria' => 'compras_stock',
                'label' => 'Compras de stock',
                'valor' => (float) FaturaCompra::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])->sum('total'),
                'origem' => 'automatico',
            ],
        ]);

        $custosManuais = $movimentos
            ->where('tipo', 'custo')
            ->groupBy('categoria')
            ->map(fn ($grupo, $categoria) => [
                'categoria' => $categoria,
                'label' => $this->labelCategoria($categoria),
                'valor' => (float) $grupo->sum('valor'),
                'origem' => 'manual',
            ])
            ->values();

        // Receita bruta vem do ReceitaFesta para ser exatamente o mesmo numero
        // que as outras associacoes veem na pagina partilhada.
        $receitas = ReceitaFesta::linhas($inicio->toDateString(), $fim->toDateString());

        $custos = $custosAutomaticos->merge($custosManuais)->values();
        $totalCustos = (float) $custos->sum('valor');
        $totalReceitas = (float) $receitas->sum('valor');

        $token = Configuracao::where('chave', AssociacaoParceiraController::CHAVE_TOKEN)->value('valor');

        return Inertia::render('ContasFesta/Index', [
            'filters' => [
                'data_inicio' => $inicio->toDateString(),
                'data_fim' => $fim->toDateString(),
            ],
            'associacoes' => AssociacaoParceira::orderBy('ordem')->orderBy('nome')->get(),
            'divisao' => AssociacaoParceira::divisao($totalReceitas),
            'linkPartilhado' => $token ? url('/contas-partilhadas/'.$token) : null,
            'custos' => $custos,
            'receitas' => $receitas,
            'movimentos' => $movimentos,
            'resumo' => [
                'total_custos' => $totalCustos,
                'total_receitas' => $totalReceitas,
                'resultado' => $totalReceitas - $totalCustos,
            ],
            'categoriasCusto' => [
                ['valor' => 'bandas', 'label' => 'Bandas'],
                ['valor' => 'luz', 'label' => 'Luz'],
                ['valor' => 'licencas', 'label' => 'Licencas'],
                ['valor' => 'seguro', 'label' => 'Seguro'],
                ['valor' => 'assar_frango', 'label' => 'Assar frango'],
                ['valor' => 'servicos', 'label' => 'Servicos'],
                ['valor' => 'outros', 'label' => 'Outros'],
            ],
            'categoriasReceita' => [
                ['valor' => 'patrocinios', 'label' => 'Patrocinios'],
                ['valor' => 'donativos_manuais', 'label' => 'Donativos manuais'],
                ['valor' => 'bar_manual', 'label' => 'Bar'],
                ['valor' => 'cafe', 'label' => 'Cafe'],
                ['valor' => 'quermesse', 'label' => 'Quermesse'],
                ['valor' => 'outros', 'label' => 'Outros'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        FestaMovimento::create($this->validated($request));

        return back()->with('success', 'Movimento registado.');
    }

    public function update(Request $request, FestaMovimento $contasFesta): RedirectResponse
    {
        $contasFesta->update($this->validated($request));

        return back()->with('success', 'Movimento atualizado.');
    }

    public function destroy(FestaMovimento $contasFesta): RedirectResponse
    {
        $contasFesta->delete();

        return back()->with('success', 'Movimento apagado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'tipo' => ['required', 'in:custo,receita'],
            'categoria' => ['required', 'string', 'max:80'],
            'descricao' => ['required', 'string', 'max:255'],
            'data' => ['nullable', 'date'],
            'valor' => ['required', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string'],
        ]);
    }

    private function labelCategoria(string $categoria): string
    {
        return [
            'compras_stock' => 'Compras de stock',
            'bandas' => 'Bandas',
            'luz' => 'Luz',
            'licencas' => 'Licencas',
            'seguro' => 'Seguro',
            'assar_frango' => 'Assar frango',
            'servicos' => 'Servicos',
            'restaurante' => 'Restaurante',
            'bar' => 'Bar',
            'bar_manual' => 'Bar (manual)',
            'cafe' => 'Cafe',
            'quermesse' => 'Quermesse',
            'patrocinios' => 'Patrocinios',
            'doacoes' => 'Doacoes',
            'donativos_manuais' => 'Donativos manuais',
        ][$categoria] ?? ucfirst(str_replace('_', ' ', $categoria));
    }
}
