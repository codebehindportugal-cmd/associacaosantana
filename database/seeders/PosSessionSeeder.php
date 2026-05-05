<?php

namespace Database\Seeders;

use App\Models\PosSession;
use Illuminate\Database\Seeder;

class PosSessionSeeder extends Seeder
{
    public function run(): void
    {
        $terminais = [
            ['nome' => 'Cafe', 'pin' => '3333', 'localizacao' => 'Cafe', 'tipo' => 'bar'],
            ['nome' => 'Bar 1', 'pin' => '1234', 'localizacao' => 'Bar 1', 'tipo' => 'bar'],
            ['nome' => 'Bar 2', 'pin' => '5678', 'localizacao' => 'Bar 2', 'tipo' => 'bar'],
            ['nome' => 'Restaurante', 'pin' => '1111', 'localizacao' => 'Restaurante', 'tipo' => 'restaurante'],
            ['nome' => 'Cotas', 'pin' => '9999', 'localizacao' => 'Tesouraria', 'tipo' => 'cotas'],
        ];

        foreach ($terminais as $terminal) {
            PosSession::updateOrCreate(
                ['nome' => $terminal['nome']],
                $terminal + ['ativo' => true],
            );
        }

        PosSession::whereNotIn('nome', collect($terminais)->pluck('nome'))->update(['ativo' => false]);
    }
}
