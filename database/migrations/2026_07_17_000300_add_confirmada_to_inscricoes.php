<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('evento_inscricoes', 'confirmada_em')) {
            Schema::table('evento_inscricoes', function (Blueprint $table) {
                // Check-in: marcada quando a pulseira é entregue
                $table->timestamp('confirmada_em')->nullable()->after('pago_em');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('evento_inscricoes', 'confirmada_em')) {
            Schema::table('evento_inscricoes', function (Blueprint $table) {
                $table->dropColumn('confirmada_em');
            });
        }
    }
};
