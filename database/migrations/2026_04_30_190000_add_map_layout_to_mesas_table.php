<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            $table->unsignedSmallInteger('mapa_x')->default(5)->after('ativa');
            $table->unsignedSmallInteger('mapa_y')->default(5)->after('mapa_x');
            $table->unsignedSmallInteger('mapa_largura')->default(18)->after('mapa_y');
            $table->unsignedSmallInteger('mapa_altura')->default(14)->after('mapa_largura');
        });

        DB::table('mesas')->whereNull('mesa_principal_id')->orderBy('numero')->get()->each(function ($mesa, $index) {
            DB::table('mesas')->where('id', $mesa->id)->update([
                'mapa_x' => 4 + (($index % 4) * 23),
                'mapa_y' => 6 + (intdiv($index, 4) * 25),
                'mapa_largura' => 18,
                'mapa_altura' => 14,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            $table->dropColumn(['mapa_x', 'mapa_y', 'mapa_largura', 'mapa_altura']);
        });
    }
};
