<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos', 'valor_recebido')) {
                $table->decimal('valor_recebido', 10, 2)->nullable()->after('total');
            }

            if (! Schema::hasColumn('pedidos', 'troco')) {
                $table->decimal('troco', 10, 2)->default(0)->after('valor_recebido');
            }

            if (! Schema::hasColumn('pedidos', 'doacao')) {
                $table->decimal('doacao', 10, 2)->default(0)->after('troco');
            }
        });
    }

    public function down(): void
    {
        // The current create_pedidos_table migration owns these columns.
    }
};
