<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caixas_diarias')) {
            Schema::create('caixas_diarias', function (Blueprint $table) {
                $table->id();
                $table->date('data');
                $table->string('ponto');
                $table->decimal('fundo_maneio', 10, 2)->default(0);
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();

                $table->unique(['data', 'ponto']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caixas_diarias');
    }
};
