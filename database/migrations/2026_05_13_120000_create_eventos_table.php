<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('subtitulo')->nullable();
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->string('periodo')->nullable();
            $table->string('localizacao')->nullable();
            $table->string('badge')->nullable();
            $table->text('descricao')->nullable();
            $table->string('cartaz')->nullable();
            $table->json('programa')->nullable();
            $table->enum('estado', ['rascunho', 'publicado'])->default('publicado');
            $table->boolean('destaque')->default(false);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
