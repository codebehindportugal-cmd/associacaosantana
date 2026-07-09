<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->decimal('custo_compra_unitario', 10, 4)->default(0)->after('preco');
            $table->string('unidade_compra', 20)->default('un')->after('custo_compra_unitario');
            $table->decimal('custo_preparacao_unitario', 10, 4)->default(0)->after('unidade_compra');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn(['custo_compra_unitario', 'unidade_compra', 'custo_preparacao_unitario']);
        });
    }
};
