<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_componentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('componente_id')->constrained('produtos')->restrictOnDelete();
            $table->decimal('quantidade', 10, 4)->default(1);
            $table->timestamps();

            $table->unique(['produto_id', 'componente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_componentes');
    }
};
