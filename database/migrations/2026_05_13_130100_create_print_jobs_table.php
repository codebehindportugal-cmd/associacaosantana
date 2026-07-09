<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impressora_id')->constrained('impressoras')->cascadeOnDelete();
            $table->nullableMorphs('printable');
            $table->string('tipo')->default('pedido');
            $table->json('payload');
            $table->enum('estado', ['pendente', 'processando', 'impresso', 'falhado'])->default('pendente')->index();
            $table->unsignedInteger('tentativas')->default(0);
            $table->text('ultimo_erro')->nullable();
            $table->timestamp('reservado_ate')->nullable();
            $table->timestamp('impresso_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
