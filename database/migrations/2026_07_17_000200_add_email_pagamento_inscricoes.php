<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('evento_inscricoes', 'email')) {
            Schema::table('evento_inscricoes', function (Blueprint $table) {
                $table->string('email')->nullable()->after('telefone');
                // null = sem pagamento online; pendente | pago | falhado
                $table->string('pagamento_estado', 20)->nullable()->after('valor_estimado');
                $table->string('pagamento_order_code', 30)->nullable()->after('pagamento_estado');
                $table->timestamp('pago_em')->nullable()->after('pagamento_order_code');
            });
        }

        if (! Schema::hasColumn('eventos', 'inscricoes_pagamento_online')) {
            Schema::table('eventos', function (Blueprint $table) {
                $table->boolean('inscricoes_pagamento_online')->default(false)->after('inscricoes_idade_crianca');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('evento_inscricoes', 'email')) {
            Schema::table('evento_inscricoes', function (Blueprint $table) {
                $table->dropColumn(['email', 'pagamento_estado', 'pagamento_order_code', 'pago_em']);
            });
        }

        if (Schema::hasColumn('eventos', 'inscricoes_pagamento_online')) {
            Schema::table('eventos', function (Blueprint $table) {
                $table->dropColumn('inscricoes_pagamento_online');
            });
        }
    }
};
