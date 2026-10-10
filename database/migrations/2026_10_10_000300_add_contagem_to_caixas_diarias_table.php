<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caixas_diarias', function (Blueprint $table) {
            // Contagem da gaveta por nota/moeda no fecho: {"50": 2, "0.5": 7, ...}
            $table->json('contagem')->nullable()->after('valor_contado');
        });
    }

    public function down(): void
    {
        Schema::table('caixas_diarias', function (Blueprint $table) {
            $table->dropColumn('contagem');
        });
    }
};
