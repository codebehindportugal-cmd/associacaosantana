<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('zona_mapas')->insert([
            ['nome' => 'WC H', 'mapa_x' => 2, 'mapa_y' => 35, 'mapa_largura' => 5, 'mapa_altura' => 20, 'tipo' => 'wc'],
            ['nome' => 'WC M', 'mapa_x' => 2, 'mapa_y' => 60, 'mapa_largura' => 5, 'mapa_altura' => 20, 'tipo' => 'wc'],
            ['nome' => 'Secretárias', 'mapa_x' => 2, 'mapa_y' => 10, 'mapa_largura' => 15, 'mapa_altura' => 20, 'tipo' => 'secretaria'],
            ['nome' => 'Balcão', 'mapa_x' => 92, 'mapa_y' => 30, 'mapa_largura' => 5, 'mapa_altura' => 50, 'tipo' => 'balcao'],
            ['nome' => 'Cama-Lebrinha', 'mapa_x' => 60, 'mapa_y' => 88, 'mapa_largura' => 20, 'mapa_altura' => 8, 'tipo' => 'zona'],
        ]);
    }

    public function down(): void
    {
        DB::table('zona_mapas')->truncate();
    }
};
