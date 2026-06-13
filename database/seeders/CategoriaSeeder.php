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
                    ['IceTea Pessego', 1.5],
                    ['IceTea Limao', 1.5],
                    ['IceTea Manga', 1.5],
                    ['Sumol Ananas', 1.5],
                    ['Sumol Laranja', 1.5],
                    ['Sangria', 3],
                    ['Vinho tinto', 2],
                    ['Vinho branco', 2],
                ],
            ],
            'Comida' => [
                'secao' => 'comida',
                'produtos' => [
                ],
            ],
            'Frango' => [
                'secao' => 'frango',
                'produtos' => [
                    ['Frango assado', 8],
                ],
            ],
            'Acompanhamentos' => [
                'secao' => 'acompanhamentos',
                'produtos' => [
                    ['Batata frita', 2.5],
                    ['Salada', 3],
                    ['Pao', 0.5],
                ],
            ],
        ];

        $produtosDisponiveis = collect($dados)
            ->flatMap(fn (array $grupo) => collect($grupo['produtos'])->pluck(0))
            ->all();

        foreach ($dados as $nome => $grupo) {
            $categoria = Categoria::updateOrCreate(['nome' => $nome], ['secao' => $grupo['secao']]);

            foreach ($grupo['produtos'] as [$produto, $preco]) {
                $categoria->produtos()->updateOrCreate(
                    ['nome' => $produto],
                    ['preco' => $preco, 'disponivel' => true]
                );
            }
        }

        Produto::whereNotIn('nome', $produtosDisponiveis)->update(['disponivel' => false]);
    }
}
