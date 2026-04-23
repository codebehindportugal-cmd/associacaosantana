<?php

namespace App\Http\Controllers;

use App\Models\Cota;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CotaController extends Controller
{
    public function index(Request $request): View
    {
        $cotas = Cota::with('socio')
            ->orderBy('ano', 'desc')
            ->orderBy('mes', 'desc')
            ->paginate(20);

        return view('cotas.index', compact('cotas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'socio_id' => 'required|exists:socios,id',
            'ano' => 'required|digits:4|integer',
            'mes' => 'required|integer|min:1|max:12',
            'tipo' => 'required|in:mensal,anual',
            'valor' => 'required|numeric|min:0',
            'data_pagamento' => 'nullable|date',
            'data_vencimento' => 'nullable|date',
            'estado' => 'required|in:pago,pendente,em_atraso',
            'metodo_pagamento' => 'nullable|in:dinheiro,mbway,transferencia',
        ]);

        Cota::create($request->only([
            'socio_id',
            'ano',
            'mes',
            'tipo',
            'valor',
            'data_pagamento',
            'data_vencimento',
            'estado',
            'metodo_pagamento',
        ]));

        return redirect()->route('cotas.index')->with('success', 'Cota criada com sucesso.');
    }

    public function update(Request $request, Cota $cota): RedirectResponse
    {
        $request->validate([
            'socio_id' => 'required|exists:socios,id',
            'ano' => 'required|digits:4|integer',
            'mes' => 'required|integer|min:1|max:12',
            'tipo' => 'required|in:mensal,anual',
            'valor' => 'required|numeric|min:0',
            'data_pagamento' => 'nullable|date',
            'data_vencimento' => 'nullable|date',
            'estado' => 'required|in:pago,pendente,em_atraso',
            'metodo_pagamento' => 'nullable|in:dinheiro,mbway,transferencia',
        ]);

        $cota->update($request->only([
            'socio_id',
            'ano',
            'mes',
            'tipo',
            'valor',
            'data_pagamento',
            'data_vencimento',
            'estado',
            'metodo_pagamento',
        ]));

        return redirect()->route('cotas.index')->with('success', 'Cota atualizada com sucesso.');
    }

    public function destroy(Cota $cota): RedirectResponse
    {
        $cota->delete();

        return redirect()->route('cotas.index')->with('success', 'Cota excluída com sucesso.');
    }
}
