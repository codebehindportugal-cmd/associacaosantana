<?php

namespace App\Http\Controllers;

use App\Models\ValorExtra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ValorExtraController extends Controller
{
    public function index(Request $request): Response
    {
        $hoje = now()->toDateString();
        $dataFiltro = $request->input('data', $hoje);

        $valores = ValorExtra::whereDate('data', $dataFiltro)
            ->with('user:id,name')
            ->orderBy('tipo')
            ->orderBy('descricao')
            ->get();

        $totalReceitas = $valores->where('tipo', 'receita')->sum('valor');
        $totalDespesas = $valores->where('tipo', 'despesa')->sum('valor');

        return Inertia::render('ValorExtras/Index', [
            'valores' => $valores,
            'dataFiltro' => $dataFiltro,
            'resumo' => [
                'total_receitas' => $totalReceitas,
                'total_despesas' => $totalDespesas,
                'saldo' => $totalReceitas - $totalDespesas,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'data' => ['required', 'date'],
            'tipo' => ['required', 'in:receita,despesa'],
            'descricao' => ['required', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'observacoes' => ['nullable', 'string'],
        ]);

        ValorExtra::create($data + ['user_id' => auth()->id()]);

        return back()->with('success', 'Valor registado com sucesso.');
    }

    public function destroy(ValorExtra $valorExtra): RedirectResponse
    {
        $valorExtra->delete();

        return back()->with('success', 'Valor removido.');
    }
}
