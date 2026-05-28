<?php

namespace App\Http\Controllers;

use App\Models\Impressora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImpressoraController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Impressoras/Index', [
            'impressoras' => Impressora::orderBy('nome')->get(),
            'secoes' => [
                'bebidas' => 'Bebidas',
                'comida' => 'Comida',
                'cozinha' => 'Cozinha',
                'sobremesas' => 'Sobremesas',
                'acompanhamentos' => 'Acompanhamentos',
                'servico' => 'Serviço',
                'bar' => 'Bar',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'secao' => ['nullable', 'string', 'in:bebidas,comida,cozinha,sobremesas,acompanhamentos,servico,bar'],
            'host' => ['required', 'string', 'max:255'],
            'porta' => ['required', 'integer', 'min:1', 'max:65535'],
            'ativa' => ['boolean'],
        ]);

        Impressora::create($validated);

        return back()->with('success', 'Impressora criada com sucesso.');
    }

    public function update(Request $request, Impressora $impressora): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'secao' => ['nullable', 'string', 'in:bebidas,comida,cozinha,sobremesas,acompanhamentos,servico,bar'],
            'host' => ['required', 'string', 'max:255'],
            'porta' => ['required', 'integer', 'min:1', 'max:65535'],
            'ativa' => ['boolean'],
        ]);

        $impressora->update($validated);

        return back()->with('success', 'Impressora atualizada com sucesso.');
    }

    public function destroy(Impressora $impressora): RedirectResponse
    {
        $impressora->delete();

        return back()->with('success', 'Impressora removida com sucesso.');
    }
}
