<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caixas_diarias', function (Blueprint $table) {
            if (! Schema::hasColumn('caixas_diarias', 'estado')) {
                $table->string('estado')->default('aberta')->after('fundo_maneio');
            }

            if (! Schema::hasColumn('caixas_diarias', 'valor_contado')) {
                $table->decimal('valor_contado', 10, 2)->nullable()->after('estado');
            }

            if (! Schema::hasColumn('caixas_diarias', 'diferenca')) {
                $table->decimal('diferenca', 10, 2)->default(0)->after('valor_contado');
            }

            if (! Schema::hasColumn('caixas_diarias', 'observacoes_fecho')) {
                $table->text('observacoes_fecho')->nullable()->after('diferenca');
            }

            if (! Schema::hasColumn('caixas_diarias', 'fechado_user_id')) {
                $table->foreignId('fechado_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('caixas_diarias', 'fechado_at')) {
                $table->timestamp('fechado_at')->nullable()->after('fechado_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('caixas_diarias', function (Blueprint $table) {
            if (Schema::hasColumn('caixas_diarias', 'fechado_user_id')) {
                $table->dropConstrainedForeignId('fechado_user_id');
            }

            $table->dropColumn([
                'estado',
                'valor_contado',
                'diferenca',
                'observacoes_fecho',
                'fechado_at',
            ]);
        });
    }
};
