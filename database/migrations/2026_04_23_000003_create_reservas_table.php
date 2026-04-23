<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mesa_id')->constrained('mesas')->cascadeOnDelete();
            $table->string('nome');
            $table->string('telefone');
            $table->date('data');
            $table->time('hora');
            $table->integer('pessoas')->default(1);
            $table->enum('estado', ['confirmada', 'cancelada'])->default('confirmada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
