<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festa_movimentos', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['custo', 'receita']);
            $table->string('categoria', 80);
            $table->string('descricao');
            $table->date('data')->nullable();
            $table->decimal('valor', 10, 2)->default(0);
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festa_movimentos');
    }
};
