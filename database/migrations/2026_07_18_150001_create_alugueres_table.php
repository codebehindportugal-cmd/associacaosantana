<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alugueres', function (Blueprint $table) {
            $table->id();
            $table->string('nome_cliente');
            $table->string('entidade')->nullable();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->text('notas')->nullable();
            $table->enum('estado', ['pendente', 'confirmado', 'cancelado', 'concluido'])->default('pendente');
            $table->decimal('caucao', 8, 2)->nullable();
            $table->boolean('caucao_devolvida')->default(false);
            $table->decimal('preco_total', 8, 2)->nullable();
            $table->boolean('pago')->default(false);
            $table->string('metodo_pagamento')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('aluguer_aluguer_opcao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluguer_id')->constrained('alugueres')->cascadeOnDelete();
            $table->foreignId('aluguer_opcao_id')->constrained('aluguer_opcoes')->cascadeOnDelete();
            $table->unique(['aluguer_id', 'aluguer_opcao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aluguer_aluguer_opcao');
        Schema::dropIfExists('alugueres');
    }
};
