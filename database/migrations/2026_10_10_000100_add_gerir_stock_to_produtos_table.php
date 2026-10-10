<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // Quando ligado, cada venda desconta do stock_atual e o produto
            // deixa de aparecer nos POS ao chegar a zero.
            $table->boolean('gerir_stock')->default(false)->after('stock_atual');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('gerir_stock');
        });
    }
};
