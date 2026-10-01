<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de mesa "A pagar" (POS restaurante): guarda quando o empregado
 * carregou em "Pedir conta". A mesa aparece a laranja no mapa enquanto o
 * pedido estiver aberto com este campo preenchido. Aditiva e nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pedidos', 'conta_pedida_em')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->timestamp('conta_pedida_em')->nullable()->after('chamado_em');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pedidos', 'conta_pedida_em')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->dropColumn('conta_pedida_em');
            });
        }
    }
};
