<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identidade impressa nos talões (cabeçalho e rodapé).
     *
     * Antes estava fixa no codigo como "ARDC Santana". Num evento partilhado
     * o papel tem de mostrar o evento e nao a associacao, por isso passa a
     * ser editavel no backoffice (Restaurante > Talao).
     *
     * Tabela de uma so linha (singleton).
     */
    public function up(): void
    {
        if (! Schema::hasTable('talao_configs')) {
            Schema::create('talao_configs', function (Blueprint $table) {
                $table->id();
                $table->string('titulo')->default('ARDC Santana');
                // Uma linha por linha impressa, centradas, logo abaixo do tipo de talao
                $table->text('cabecalho')->nullable();
                $table->text('rodape')->nullable();
                $table->boolean('rodape_em_pedidos')->default(false);
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        if (DB::table('talao_configs')->count() === 0) {
            DB::table('talao_configs')->insert([
                'titulo' => 'CARVALHAL FEST',
                'cabecalho' => "2.ª Edição\n9, 10 e 11 de Outubro 2026\nCarvalhal Benfeito - Caldas da Rainha",
                'rodape' => "Organização: Junta de Freguesia\nde Carvalhal Benfeito e Coletividades\nEste documento não serve de fatura",
                'rodape_em_pedidos' => false,
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('talao_configs');
    }
};
