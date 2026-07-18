<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aluguer_opcoes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('descricao')->nullable();
            $table->decimal('preco_extra', 8, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });

        // Opções padrão
        DB::table('aluguer_opcoes')->insert([
            ['nome' => 'Com câmara fotográfica/vídeo', 'descricao' => 'Inclui câmara de vigilância e gravação do evento', 'preco_extra' => 0, 'ativo' => true, 'ordem' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Com louça',                    'descricao' => 'Inclui louça completa (pratos, talheres, copos)', 'preco_extra' => 0, 'ativo' => true, 'ordem' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Com cozinha',                  'descricao' => 'Acesso à cozinha equipada', 'preco_extra' => 0, 'ativo' => true, 'ordem' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Com sistema de som',           'descricao' => 'Inclui coluna de som e microfone', 'preco_extra' => 0, 'ativo' => true, 'ordem' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Com projetor',                 'descricao' => 'Inclui projetor e tela de projeção', 'preco_extra' => 0, 'ativo' => true, 'ordem' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('aluguer_opcoes');
    }
};
