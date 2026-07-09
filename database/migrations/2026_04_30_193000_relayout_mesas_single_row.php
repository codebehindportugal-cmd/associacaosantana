<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        DB::table('mesas')->whereNull('mesa_principal_id')->orderBy('numero')->get()->each(function ($mesa, $index) {
            DB::table('mesas')->where('id', $mesa->id)->update([
                'mapa_x' => 4 + (($index % 4) * 23),
                'mapa_y' => 6 + (intdiv($index, 4) * 25),
                'mapa_largura' => 18,
                'mapa_altura' => 14,
            ]);
        });
    }
};
