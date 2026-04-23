<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SecaoController extends Controller
{
    public function bebidas(): Response
    {
        $produtos = Produto::where('disponivel', true)
            ->whereHas('categoria', function ($query) {
                $query->where('secao', 'bebidas');
            })
            ->orderBy('nome')
            ->get();

        return response()->view('secoes.bebidas', compact('produtos'))
            ->header('Refresh', '30');
    }

    public function sobremesas(): Response
    {
        $produtos = Produto::where('disponivel', true)
            ->whereHas('categoria', function ($query) {
                $query->where('secao', 'sobremesas');
            })
            ->orderBy('nome')
            ->get();

        return response()->view('secoes.sobremesas', compact('produtos'))
            ->header('Refresh', '30');
    }

    public function acompanhamentos(): Response
    {
        $produtos = Produto::where('disponivel', true)
            ->whereHas('categoria', function ($query) {
                $query->where('secao', 'acompanhamentos');
            })
            ->orderBy('nome')
            ->get();

        return response()->view('secoes.acompanhamentos', compact('produtos'))
            ->header('Refresh', '30');
    }
}
