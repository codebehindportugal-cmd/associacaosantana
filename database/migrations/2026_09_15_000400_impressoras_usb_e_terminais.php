<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1) Impressoras ligadas por USB ao computador do posto, alem das de rede.
     *    Sem IP nao ha host nem porta: passa a haver "dispositivo" (o nome da
     *    impressora no Windows, ou /dev/usb/lp0 em Linux).
     * 2) "agente" identifica que computador trata de cada impressora, para que
     *    com varios postos cada agente so va buscar os trabalhos dele.
     * 3) Cada terminal POS pode apontar para a sua impressora, para que o talao
     *    saia no posto onde a venda foi feita e nao no primeiro da lista.
     */
    public function up(): void
    {
        Schema::table('impressoras', function (Blueprint $table) {
            if (! Schema::hasColumn('impressoras', 'tipo')) {
                $table->string('tipo', 20)->default('rede')->after('secao');
            }
            if (! Schema::hasColumn('impressoras', 'dispositivo')) {
                $table->string('dispositivo')->nullable()->after('porta');
            }
            if (! Schema::hasColumn('impressoras', 'agente')) {
                $table->string('agente', 60)->nullable()->index()->after('dispositivo');
            }
        });

        // Numa impressora USB nao ha host nenhum para guardar
        Schema::table('impressoras', function (Blueprint $table) {
            $table->string('host')->nullable()->change();
        });

        if (! Schema::hasColumn('pos_sessions', 'impressora_id')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->foreignId('impressora_id')->nullable()->after('localizacao')
                    ->constrained('impressoras')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_sessions', 'impressora_id')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('impressora_id');
            });
        }

        Schema::table('impressoras', function (Blueprint $table) {
            foreach (['tipo', 'dispositivo', 'agente'] as $coluna) {
                if (Schema::hasColumn('impressoras', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
