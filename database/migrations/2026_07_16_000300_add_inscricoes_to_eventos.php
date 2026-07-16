<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('eventos', 'inscricoes_ativas')) {
            Schema::table('eventos', function (Blueprint $table) {
                $table->boolean('inscricoes_ativas')->default(false)->after('destaque');
                $table->unsignedInteger('inscricoes_limite')->nullable()->after('inscricoes_ativas');
                // Opções de escolha única, ex.: ["Só caminhar", "Caminhar e almoçar"]
                $table->json('inscricoes_opcoes')->nullable()->after('inscricoes_limite');
                // Pedir nº de crianças e idades (ex.: almoço dos sócios)
                $table->boolean('inscricoes_pede_idades')->default(false)->after('inscricoes_opcoes');
            });
        }

        if (! Schema::hasTable('evento_inscricoes')) {
            Schema::create('evento_inscricoes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
                $table->string('nome');
                $table->string('telefone', 30);
                $table->unsignedInteger('num_pessoas')->default(1);
                $table->string('opcao')->nullable();
                $table->unsignedInteger('num_criancas')->nullable();
                $table->string('idades_criancas')->nullable();
                $table->text('observacoes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_inscricoes');

        if (Schema::hasColumn('eventos', 'inscricoes_ativas')) {
            Schema::table('eventos', function (Blueprint $table) {
                $table->dropColumn(['inscricoes_ativas', 'inscricoes_limite', 'inscricoes_opcoes', 'inscricoes_pede_idades']);
            });
        }
    }
};
