<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caixas_diarias', function (Blueprint $table) {
            // Fecho feito no POS do bar: nao ha utilizador, fica o nome do operador
            $table->string('fechado_por_nome', 80)->nullable()->after('fechado_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('caixas_diarias', function (Blueprint $table) {
            $table->dropColumn('fechado_por_nome');
        });
    }
};
