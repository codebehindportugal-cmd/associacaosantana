<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POS do pre-pagamento: ver as senhas anteriores, reimprimir (2a via) e
     * anular a pedido do cliente. Guarda quem anulou, quando e porque, e as
     * opcoes de impressao da senha (seccoes juntas) para a 2a via sair igual.
     */
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos', 'juntar')) {
                $table->json('juntar')->nullable();
            }
            if (! Schema::hasColumn('pedidos', 'reimpressoes')) {
                $table->unsignedSmallInteger('reimpressoes')->default(0);
            }
            if (! Schema::hasColumn('pedidos', 'anulado_em')) {
                $table->timestamp('anulado_em')->nullable();
                $table->string('anulado_por')->nullable();
                $table->string('motivo_anulacao', 255)->nullable();
                $table->decimal('valor_devolvido', 10, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            foreach (['juntar', 'reimpressoes', 'anulado_em', 'anulado_por', 'motivo_anulacao', 'valor_devolvido'] as $coluna) {
                if (Schema::hasColumn('pedidos', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
