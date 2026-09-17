<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eventos com pre-pagamento (caminhada, tasquinhas): o cliente paga tudo
     * a cabeca e leva um talao por unidade para ir levantar depois. Nesses
     * eventos nao sai a conta agrupada — so os taloes individuais.
     *
     * Fora destes eventos mantem-se o normal: so os produtos marcados como
     * "talao individual" saem um por unidade, o resto sai num talao so.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('talao_configs', 'prepago_apenas_individuais')) {
            Schema::table('talao_configs', function (Blueprint $table) {
                $table->boolean('prepago_apenas_individuais')->default(false)->after('rodape_em_pedidos');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('talao_configs', 'prepago_apenas_individuais')) {
            Schema::table('talao_configs', function (Blueprint $table) {
                $table->dropColumn('prepago_apenas_individuais');
            });
        }
    }
};
