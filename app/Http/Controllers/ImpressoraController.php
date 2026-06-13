<?php

namespace App\Http\Controllers;

use App\Models\Impressora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImpressoraController extends Controller
{
    private const SECOES = [
        'bebidas' => 'Bebidas',
        'frango' => 'Frango',
        'acompanhamentos' => 'Acompanhamentos',
        'comida' => 'Comida',
        'cozinha' => 'Cozinha',
        'sobremesas' => 'Sobremesas',
        'servico' => 'Servico',
        'bar' => 'Bar',
        'contas' => 'Contas',
    ];

    public function index(): Response
    {
        return Inertia::render('Impressoras/Index', [
            'impressoras' => Impressora::orderBy('nome')->get(),
            'secoes' => self::SECOES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        Impressora::create($validated);

        return back()->with('success', 'Impressora criada com sucesso.');
    }

    public function update(Request $request, Impressora $impressora): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $impressora->update($validated);

        return back()->with('success', 'Impressora atualizada com sucesso.');
    }

    public function destroy(Impressora $impressora): RedirectResponse
    {
        $impressora->delete();

        return back()->with('success', 'Impressora removida com sucesso.');
    }

    private function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'secao' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::SECOES))],
            'host' => ['required', 'string', 'max:255'],
            'porta' => ['required', 'integer', 'min:1', 'max:65535'],
            'ativa' => ['boolean'],
        ];
    }
}
