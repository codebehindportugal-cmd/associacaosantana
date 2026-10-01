<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado das impressoras no backoffice: guarda quando o agente confirmou o
 * ultimo talao impresso (ultimo_ok_at) e quando reportou o ultimo erro
 * (ultimo_erro_at) para cada impressora. Aditiva e nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impressoras', function (Blueprint $table) {
            if (! Schema::hasColumn('impressoras', 'ultimo_ok_at')) {
                $table->timestamp('ultimo_ok_at')->nullable();
            }
            if (! Schema::hasColumn('impressoras', 'ultimo_erro_at')) {
                $table->timestamp('ultimo_erro_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('impressoras', function (Blueprint $table) {
            foreach (['ultimo_ok_at', 'ultimo_erro_at'] as $coluna) {
                if (Schema::hasColumn('impressoras', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
