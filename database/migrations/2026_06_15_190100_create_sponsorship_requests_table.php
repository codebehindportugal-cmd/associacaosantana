<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_requests', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('empresa');
            $table->string('email');
            $table->string('telefone')->nullable();
            $table->text('mensagem')->nullable();
            $table->boolean('aceita_contacto')->default(false);
            $table->enum('estado', ['pendente', 'contactado', 'confirmado', 'recusado'])->default('pendente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_requests');
    }
};
