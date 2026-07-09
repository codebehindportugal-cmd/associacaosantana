<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        collect([
            ['nome' => 'WC H.', 'mapa_x' => 3, 'mapa_y' => 46, 'mapa_largura' => 4, 'mapa_altura' => 12, 'tipo' => 'texto'],
            ['nome' => 'WC M.', 'mapa_x' => 3, 'mapa_y' => 60, 'mapa_largura' => 4, 'mapa_altura' => 12, 'tipo' => 'texto'],
            ['nome' => 'Sobremesas', 'mapa_x' => 11, 'mapa_y' => 14, 'mapa_largura' => 10, 'mapa_altura' => 18, 'tipo' => 'zona'],
            ['nome' => 'Caixa / Bebidas', 'mapa_x' => 9, 'mapa_y' => 75, 'mapa_largura' => 8, 'mapa_altura' => 17, 'tipo' => 'zona'],
            ['nome' => 'Entrada', 'mapa_x' => 19, 'mapa_y' => 94, 'mapa_largura' => 7, 'mapa_altura' => 4, 'tipo' => 'entrada'],
            ['nome' => 'Palco', 'mapa_x' => 95, 'mapa_y' => 31, 'mapa_largura' => 5, 'mapa_altura' => 14, 'tipo' => 'texto'],
        ])->each(fn ($zona) => DB::table('zona_mapas')->updateOrInsert(
            ['nome' => $zona['nome']],
            $zona
        ));
    }

    public function down(): void
    {
        DB::table('zona_mapas')
            ->whereIn('nome', ['WC H.', 'WC M.', 'Sobremesas', 'Caixa / Bebidas', 'Entrada', 'Palco'])
            ->delete();
    }
};
