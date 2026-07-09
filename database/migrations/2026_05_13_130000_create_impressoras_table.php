<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impressoras', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('secao')->nullable()->index();
            $table->string('host');
            $table->unsignedSmallInteger('porta')->default(9100);
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impressoras');
    }
};
