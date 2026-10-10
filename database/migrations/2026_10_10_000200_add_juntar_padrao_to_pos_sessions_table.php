<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            // Pre-pagamento: que grupos (cozinha, sobremesas, bebidas) saem
            // por omissao numa folha neste posto. O operador pode mudar venda a venda.
            $table->json('juntar_padrao')->nullable()->after('impressao_navegador');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropColumn('juntar_padrao');
        });
    }
};
