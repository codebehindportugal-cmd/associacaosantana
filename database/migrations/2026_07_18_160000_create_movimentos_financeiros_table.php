<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentos_financeiros', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['entrada', 'saida']);
            $table->string('descricao', 255);
            $table->decimal('valor', 10, 2);
            $table->date('data');
            $table->string('categoria', 100)->nullable();
            $table->enum('conta', ['banco', 'prazo'])->default('banco');
            $table->string('referencia', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentos_financeiros');
    }
};
