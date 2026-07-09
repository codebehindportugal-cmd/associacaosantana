<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos', 'pos_id')) {
                $table->foreignId('pos_id')->nullable()->after('user_id')->constrained('pos_sessions')->nullOnDelete();
            }

            if (! Schema::hasColumn('pedidos', 'metodo_pagamento')) {
                $table->enum('metodo_pagamento', ['dinheiro', 'mbway', 'multibanco', 'transferencia'])->nullable()->after('doacao');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'pos_id')) {
                $table->dropConstrainedForeignId('pos_id');
            }

            if (Schema::hasColumn('pedidos', 'metodo_pagamento')) {
                $table->dropColumn('metodo_pagamento');
            }
        });
    }
};
