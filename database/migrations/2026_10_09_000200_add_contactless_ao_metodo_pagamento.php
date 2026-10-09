<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pre-pagamento: pagamento por contactless (cartao/telemovel no terminal).
     * A coluna e um ENUM e recusava o valor novo.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            return;
        }

        DB::statement("ALTER TABLE pedidos MODIFY metodo_pagamento ENUM('dinheiro','mbway','multibanco','transferencia','contactless') NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            return;
        }

        DB::statement("UPDATE pedidos SET metodo_pagamento = 'multibanco' WHERE metodo_pagamento = 'contactless'");
        DB::statement("ALTER TABLE pedidos MODIFY metodo_pagamento ENUM('dinheiro','mbway','multibanco','transferencia') NULL");
    }
};
