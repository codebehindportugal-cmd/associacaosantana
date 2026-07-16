<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pedidos', 'observacoes')) {
            return;
        }

        Schema::table('pedidos', function (Blueprint $table) {
            $table->text('observacoes')->nullable()->after('metodo_pagamento');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('observacoes');
        });
    }
};
