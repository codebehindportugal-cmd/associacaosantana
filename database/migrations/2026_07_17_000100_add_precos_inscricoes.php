<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('eventos', 'inscricoes_preco')) {
            Schema::table('eventos', function (Blueprint $table) {
                // Preço por pessoa (adulto). Nulo = grátis / sem preço definido
                $table->decimal('inscricoes_preco', 8, 2)->nullable()->after('inscricoes_opcoes');
                // Preço por criança. Nulo = igual ao adulto; 0 = grátis
                $table->decimal('inscricoes_preco_crianca', 8, 2)->nullable()->after('inscricoes_preco');
                // Considera-se criança até esta idade (inclusive)
                $table->unsignedTinyInteger('inscricoes_idade_crianca')->nullable()->after('inscricoes_preco_crianca');
            });
        }

        if (! Schema::hasColumn('evento_inscricoes', 'valor_estimado')) {
            Schema::table('evento_inscricoes', function (Blueprint $table) {
                $table->decimal('valor_estimado', 8, 2)->nullable()->after('observacoes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('eventos', 'inscricoes_preco')) {
            Schema::table('eventos', function (Blueprint $table) {
                $table->dropColumn(['inscricoes_preco', 'inscricoes_preco_crianca', 'inscricoes_idade_crianca']);
            });
        }

        if (Schema::hasColumn('evento_inscricoes', 'valor_estimado')) {
            Schema::table('evento_inscricoes', function (Blueprint $table) {
                $table->dropColumn('valor_estimado');
            });
        }
    }
};
