<?php

namespace Database\Seeders;

use App\Models\Mesa;
use Illuminate\Database\Seeder;

class MesaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 41) as $numero) {
            Mesa::updateOrCreate(['numero' => $numero], [
                'mesa_principal_id' => null,
                'nome' => 'Mesa '.$numero,
                'capacidade' => 10,
                'lugares_inicio' => 1,
                'lugares_fim' => 10,
                'localizacao' => 'sala',
                'estado' => 'livre',
                'ativa' => true,
                'mapa_x' => 1 + ((($numero - 1) % 11) * 9),
                'mapa_y' => 5 + (intdiv($numero - 1, 11) * 23),
                'mapa_largura' => 7,
                'mapa_altura' => 15,
            ]);
        }
    }
}
