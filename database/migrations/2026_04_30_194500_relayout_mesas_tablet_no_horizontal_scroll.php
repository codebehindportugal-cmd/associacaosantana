<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('mesas')->whereNull('mesa_principal_id')->orderBy('numero')->get()->each(function ($mesa, $index) {
            DB::table('mesas')->where('id', $mesa->id)->update([
                'mapa_x' => 1 + ($index * 9),
                'mapa_y' => 38,
                'mapa_largura' => 7,
                'mapa_altura' => 16,
            ]);
        });
    }

    public function down(): void
    {
        DB::table('mesas')->whereNull('mesa_principal_id')->orderBy('numero')->get()->each(function ($mesa, $index) {
            DB::table('mesas')->where('id', $mesa->id)->update([
                'mapa_x' => 2 + ($index * 9),
                'mapa_y' => 34,
                'mapa_largura' => 7,
                'mapa_altura' => 18,
            ]);
        });
    }
};
