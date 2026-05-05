<?php

namespace App\Http\Controllers;

use App\Models\Cota;
use App\Models\Socio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosCotasController extends Controller
{
    public function index(): Response
    {
        $sociosEmAtraso = Socio::emAtraso()->count();
        $cobradosHoje = Cota::whereDate('data_pagamento', today())->sum('valor');
        $cotasHoje = Cota::whereDate('data_pagamento', today())->count();

        return Inertia::render('PosCotas/Index', compact('sociosEmAtraso', 'cobradosHoje', 'cotasHoje'));
    }

    public function pesquisa(Request $request): Response
    {
        $query = trim((string) $request->q);
        $socios = Socio::query()
            ->when($query !== '', fn ($builder) => $builder->where(function ($where) use ($query) {
                $where->where('nome', 'like', "%{$query}%")
                    ->orWhere('numero_socio', 'like', "%{$query}%")
                    ->orWhere('telefone', 'like', "%{$query}%");
            }))
            ->with(['cotas' => fn ($q) => $q->latest()->limit(12)])
            ->orderBy('nome')
            ->limit(10)
            ->get();

        return Inertia::render('PosCotas/Pesquisa', ['socios' => $socios, 'query' => $query]);
    }

    public function socio(Socio $socio): Response
    {
        $cotasRecentes = $socio->cotas()->latest()->limit(24)->get();
        $mesesEmAtraso = $socio->meses_em_atraso;
        $valorEmDivida = $socio->valor_em_divida;

        return Inertia::render('PosCotas/Socio', compact('socio', 'cotasRecentes', 'mesesEmAtraso', 'valorEmDivida'));
    }

    public function novoSocioForm(): Response
    {
        $proximo = ((int) Socio::max('numero_socio')) + 1;

        return Inertia::render('PosCotas/NovoSocio', ['proximoNumero' => (string) max(1, $proximo)]);
    }

    public function registarPagamento(Request $request, Socio $socio): RedirectResponse
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:mensal,anual'],
            'meses' => ['nullable', 'array'],
            'meses.*' => ['integer', 'between:1,12'],
            'ano' => ['required', 'integer', 'min:2020', 'max:2100'],
            'valor' => ['required', 'numeric', 'min:0'],
            'metodo_pagamento' => ['required', 'in:dinheiro,mbway,transferencia'],
            'valor_recebido' => ['nullable', 'numeric', 'min:0'],
        ]);

        $ultimaCota = null;

        if ($data['tipo'] === 'anual') {
            $ultimaCota = $socio->cotas()->create([
                'tipo' => 'anual',
                'ano' => $data['ano'],
                'mes' => null,
                'valor' => $data['valor'],
                'estado' => 'pago',
                'data_pagamento' => today(),
                'data_vencimento' => today(),
                'metodo_pagamento' => $data['metodo_pagamento'],
            ]);
        } else {
            foreach (($data['meses'] ?? []) as $mes) {
                $ultimaCota = $socio->cotas()->updateOrCreate(
                    ['tipo' => 'mensal', 'ano' => $data['ano'], 'mes' => $mes],
                    [
                        'valor' => 5,
                        'estado' => 'pago',
                        'data_pagamento' => today(),
                        'data_vencimento' => now()->setDate($data['ano'], $mes, 1)->endOfMonth()->toDateString(),
                        'metodo_pagamento' => $data['metodo_pagamento'],
                    ],
                );
            }
        }

        if (! $ultimaCota) {
            return back()->withErrors(['meses' => 'Seleciona pelo menos um mes.']);
        }

        return to_route('pos.cotas.recibo', $ultimaCota);
    }

    public function novoSocio(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'numero_socio' => ['required', 'string', 'max:50', 'unique:socios,numero_socio'],
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:50'],
        ]);

        $socio = Socio::create($data + ['data_inscricao' => today(), 'estado' => 'ativo']);

        return to_route('pos.cotas.socio', $socio);
    }

    public function recibo(Cota $cota): Response
    {
        return Inertia::render('PosCotas/Recibo', ['cota' => $cota->load('socio')]);
    }

    public function emAtraso(): Response
    {
        $socios = Socio::emAtraso()
            ->ativos()
            ->with(['cotas' => fn ($q) => $q->emAtraso()])
            ->orderBy('nome')
            ->get()
            ->sortByDesc('meses_em_atraso')
            ->values();

        return Inertia::render('PosCotas/EmAtraso', ['socios' => $socios]);
    }

    public function resumoDia(): Response
    {
        $cotas = Cota::whereDate('data_pagamento', today())->with('socio')->latest()->get();

        return Inertia::render('PosCotas/ResumoDia', ['cotas' => $cotas]);
    }
}
