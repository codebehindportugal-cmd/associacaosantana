<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Inertia\Inertia;
use Inertia\Response;

class PrecarioController extends Controller
{
    public function __invoke(): Response
    {
        $produtos = Produto::with('categoria')
            ->disponiveisRestaurante()
            ->orderBy('nome')
            ->get()
            ->groupBy(fn (Produto $produto) => $produto->categoria?->nome ?? 'Outros')
            ->map(fn ($grupo) => $grupo->map(fn (Produto $produto) => [
                'id' => $produto->id,
                'nome' => $produto->nome,
                'preco' => (float) $produto->preco,
                'categoria' => [
                    'nome' => $produto->categoria?->nome,
                    'secao' => $produto->categoria?->secao,
                ],
            ])->values())
            ->toArray();

        return Inertia::render('Public/Precario', [
            'produtos' => $produtos,
        ]);
    }
}
