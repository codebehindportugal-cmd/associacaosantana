<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')->constrained('socios')->cascadeOnDelete();
            $table->year('ano');
            $table->tinyInteger('mes')->nullable();
            $table->enum('tipo', ['mensal', 'anual'])->default('mensal');
            $table->decimal('valor', 10, 2);
            $table->date('data_pagamento')->nullable();
            $table->date('data_vencimento');
            $table->enum('estado', ['pago', 'pendente', 'em_atraso'])->default('pendente');
            $table->enum('metodo_pagamento', ['dinheiro', 'mbway', 'transferencia'])->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotas');
    }
};
