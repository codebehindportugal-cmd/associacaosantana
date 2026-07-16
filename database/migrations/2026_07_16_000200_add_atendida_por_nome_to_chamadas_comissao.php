<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('chamadas_comissao', 'atendida_por_nome')) {
            Schema::table('chamadas_comissao', function (Blueprint $table) {
                $table->string('atendida_por_nome')->nullable()->after('atendida_por_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('chamadas_comissao', 'atendida_por_nome')) {
            Schema::table('chamadas_comissao', function (Blueprint $table) {
                $table->dropColumn('atendida_por_nome');
            });
        }
    }
};
