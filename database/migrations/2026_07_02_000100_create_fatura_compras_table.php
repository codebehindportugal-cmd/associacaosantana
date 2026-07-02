<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fatura_compras', function (Blueprint $table) {
            $table->id();
            $table->string('fornecedor')->nullable();
            $table->string('numero')->nullable();
            $table->date('data');
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fatura_compras');
    }
};
