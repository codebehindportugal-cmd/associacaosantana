<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chamadas_comissao', function (Blueprint $table) {
            $table->id();
            $table->string('operador_nome');
            $table->string('local');
            $table->foreignId('atendida_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('atendida_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chamadas_comissao');
    }
};
