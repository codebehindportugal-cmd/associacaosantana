<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Produto;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        Produto::where('nome', 'like', '%Ã%')->delete();

        $dados = [
            'Bebidas' => [
                'secao' => 'bebidas',
                'produtos' => [
                    ['Agua', 1],
                    ['Cerveja', 1.5],
                    ['Vinho tinto', 2],
                    ['Refrigerante', 1.5],
                    ['Shot', 2.5],
                    ['Cocktail', 5],
                    ['Sumo natural', 2],
                ],
            ],
            'Comida' => [
                'secao' => 'comida',
                'produtos' => [
                    ['Sopa', 2],
                    ['Frango assado', 8],
                    ['Bacalhau', 10],
                    ['Bitoque', 7],
                    ['Hamburguer no prato', 7.5],
                    ['Prego no prato', 8],
                    ['Omelete', 6],
                    ['Salada', 3],
                    ['Pao', 0.5],
                    ['Batata frita', 2.5],
                    ['Arroz', 2],
                    ['Entrada mista', 4],
                ],
            ],
            'Sobremesas' => [
                'secao' => 'sobremesas',
                'produtos' => [
                    ['Bolo de chocolate', 2.5],
                    ['Gelado', 2],
                    ['Pudim', 2],
                    ['Mousse', 2.5],
                    ['Fruta da epoca', 2],
                ],
            ],
        ];

        foreach ($dados as $nome => $grupo) {
            $categoria = Categoria::updateOrCreate(['nome' => $nome], ['secao' => $grupo['secao']]);

            foreach ($grupo['produtos'] as [$produto, $preco]) {
                $categoria->produtos()->updateOrCreate(
                    ['nome' => $produto],
                    ['preco' => $preco, 'disponivel' => true]
                );
            }
        }
    }
}
