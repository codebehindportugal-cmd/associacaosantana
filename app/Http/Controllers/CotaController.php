<?php

namespace App\Http\Controllers;

use App\Models\Cota;
use App\Models\Socio;
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

        return Inertia::render('Cotas/Index', [
            'cotas' => (clone $query)->latest('ano')->latest('mes')->paginate(20)->withQueryString(),
            'totais' => [
                'cobrado' => (float) (clone $query)->where('estado', 'pago')->sum('valor'),
                'pendente' => (float) (clone $query)->whereIn('estado', ['pendente', 'em_atraso'])->sum('valor'),
            ],
            'filters' => $request->only('ano', 'mes', 'estado'),
            'socios' => Socio::ativos()->orderBy('nome')->get(['id', 'nome', 'numero_socio']),
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

    public function gerarCotas(): RedirectResponse
    {
        $hoje = now();
        Socio::ativos()->get()->each(function (Socio $socio) use ($hoje) {
            Cota::firstOrCreate([
                'socio_id' => $socio->id,
                'ano' => $hoje->year,
                'mes' => $hoje->month,
                'tipo' => 'mensal',
            ], [
                'valor' => 5,
                'data_vencimento' => $hoje->copy()->endOfMonth(),
                'estado' => 'pendente',
            ]);
        });

        return back()->with('success', 'Cotas mensais geradas.');
    }
}
