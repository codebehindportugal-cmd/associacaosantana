<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-pagamento: alem das senhas do cliente, o posto pode mandar a comida
 * (frango, acompanhamentos, comida) para outra impressora, para a cozinha
 * comecar a preparar. Escolhe-se a impressora por posto e, venda a venda,
 * se envia ou nao.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_sessions', 'impressora_preparacao_id')) {
                $table->foreignId('impressora_preparacao_id')->nullable()->after('impressora_id')
                    ->constrained('impressoras')->nullOnDelete();
            }
            if (! Schema::hasColumn('pos_sessions', 'preparacao_padrao')) {
                $table->boolean('preparacao_padrao')->default(true)->after('impressora_preparacao_id');
            }
        });

        Schema::table('pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos', 'enviado_preparacao_id')) {
                // Impressora para onde o pedido foi mandado preparar (para avisar se for anulado)
                $table->unsignedBigInteger('enviado_preparacao_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'enviado_preparacao_id')) {
                $table->dropColumn('enviado_preparacao_id');
            }
        });

        Schema::table('pos_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('pos_sessions', 'impressora_preparacao_id')) {
                $table->dropConstrainedForeignId('impressora_preparacao_id');
            }
            if (Schema::hasColumn('pos_sessions', 'preparacao_padrao')) {
                $table->dropColumn('preparacao_padrao');
            }
        });
    }
};
