<?php

namespace App\Http\Controllers;

use App\Models\Cota;
use App\Models\Socio;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CotaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:cotas.ver')->only(['index', 'show']);
        $this->middleware('permission:cotas.criar')->only(['create', 'store']);
        $this->middleware('permission:cotas.editar')->only(['edit', 'update']);
        $this->middleware('permission:cotas.apagar')->only('destroy');
        $this->middleware('permission:cotas.gerar')->only('gerarCotas');
    }

    public function index(Request $request): Response
    {
        $query = Cota::with('socio')
            ->when($request->ano, fn ($q, $ano) => $q->where('ano', $ano))
            ->when($request->mes, fn ($q, $mes) => $q->where('mes', $mes))
            ->when($request->estado, fn ($q, $estado) => $q->where('estado', $estado));

        $cotas = (clone $query)->latest('ano')->latest('mes')->paginate(20)->withQueryString();

        // O Socio traz cota_em_dia, anos_em_atraso e valor_em_divida em $appends,
        // e cada um faz a sua query. Nesta listagem nao sao usados: escondidos,
        // sao menos 3 queries por linha.
        $cotas->getCollection()->each(
            fn ($cota) => $cota->socio?->makeHidden(['cota_em_dia', 'anos_em_atraso', 'valor_em_divida']),
        );

        return Inertia::render('Cotas/Index', [
            'cotas' => $cotas,
            'totais' => [
                'cobrado' => (float) (clone $query)->where('estado', 'pago')->sum('valor'),
                'pendente' => (float) (clone $query)->whereIn('estado', ['pendente', 'em_atraso'])->sum('valor'),
            ],
            'filters' => $request->only('ano', 'mes', 'estado'),
            // Mesma razao do makeHidden acima, e aqui pesa mais: com centenas de
            // socios, os 3 atributos calculados faziam 3 queries por cada um
            'socios' => Socio::ativos()
                ->orderBy('nome')
                ->get(['id', 'nome', 'numero_socio', 'morada'])
                ->makeHidden(['cota_em_dia', 'anos_em_atraso', 'valor_em_divida']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Cotas/Index');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'socio_id' => ['required', 'exists:socios,id'],
            'ano' => ['required', 'integer'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'tipo' => ['required', 'in:mensal,anual'],
            'valor' => ['required', 'numeric', 'min:0'],
            'data_pagamento' => ['nullable', 'date'],
            'data_vencimento' => ['required', 'date'],
            'estado' => ['required', 'in:pago,pendente,em_atraso'],
            'metodo_pagamento' => ['nullable', 'in:dinheiro,mbway,transferencia'],
            'observacoes' => ['nullable', 'string'],
        ]);

        Cota::create($data);

        return back()->with('success', 'Cota registada com sucesso.');
    }

    public function show(Cota $cota): Response
    {
        return Inertia::render('Cotas/Index', ['cota' => $cota->load('socio')]);
    }

    public function edit(Cota $cota): Response
    {
        return Inertia::render('Cotas/Index', ['cota' => $cota->load('socio')]);
    }

    public function update(Request $request, Cota $cota): RedirectResponse
    {
        $cota->update($request->validate([
            'estado' => ['required', 'in:pago,pendente,em_atraso'],
            'data_pagamento' => ['nullable', 'date'],
            'metodo_pagamento' => ['nullable', 'in:dinheiro,mbway,transferencia'],
            'observacoes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Cota atualizada.');
    }

    public function destroy(Cota $cota): RedirectResponse
    {
        $cota->delete();

        return back()->with('success', 'Cota apagada.');
    }

    /**
     * Cria a cota anual de cada socio ativo que ainda nao a tenha.
     *
     * A cota e anual e simbolica (5 EUR). Antes gerava uma cota por mes, o que
     * dava 60 EUR por ano a quem paga 5.
     */
    public function gerarCotas(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'ano' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $ano = (int) ($dados['ano'] ?? now()->year);
        $vencimento = Carbon::create($ano, 12, 31)->toDateString();

        // Conta qualquer cota do ano, mensal ou anual, para nao duplicar
        // quem ainda tenha registos do tempo da cota mensal
        $jaTem = Cota::where('ano', $ano)->pluck('socio_id')->unique()->all();

        $novas = Socio::ativos()
            ->when($jaTem !== [], fn ($query) => $query->whereNotIn('id', $jaTem))
            ->pluck('id')
            ->map(fn ($socioId) => [
                'socio_id' => $socioId,
                'ano' => $ano,
                'mes' => null,
                'tipo' => 'anual',
                'valor' => Socio::VALOR_COTA_ANUAL,
                'data_vencimento' => $vencimento,
                'estado' => 'pendente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        if ($novas->isEmpty()) {
            return back()->with('success', 'As cotas de '.$ano.' já estavam todas geradas.');
        }

        $novas->chunk(500)->each(fn ($lote) => Cota::insert($lote->values()->all()));

        return back()->with('success', $novas->count().' cota(s) anuais geradas para '.$ano.'.');
    }
}
