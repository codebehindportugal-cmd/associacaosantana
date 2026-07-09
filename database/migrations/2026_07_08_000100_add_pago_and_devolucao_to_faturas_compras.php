<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fatura_compras', function (Blueprint $table) {
            $table->boolean('pago')->default(false)->after('total');
        });

        Schema::table('fatura_compra_items', function (Blueprint $table) {
            $table->decimal('quantidade_devolvida', 10, 3)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('fatura_compras', function (Blueprint $table) {
            $table->dropColumn('pago');
        });

        Schema::table('fatura_compra_items', function (Blueprint $table) {
            $table->dropColumn('quantidade_devolvida');
        });
    }
};
