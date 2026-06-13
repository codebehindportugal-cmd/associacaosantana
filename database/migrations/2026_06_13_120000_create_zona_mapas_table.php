<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zona_mapas', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->unsignedSmallInteger('mapa_x')->default(10);
            $table->unsignedSmallInteger('mapa_y')->default(10);
            $table->unsignedSmallInteger('mapa_largura')->default(20);
            $table->unsignedSmallInteger('mapa_altura')->default(15);
            $table->string('tipo')->default('zona'); // zona, wc, etc
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zona_mapas');
    }
};
