<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->boolean('disponivel_restaurante')->default(true)->after('disponivel');
            $table->boolean('disponivel_bar')->default(true)->after('disponivel_restaurante');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn(['disponivel_restaurante', 'disponivel_bar']);
        });
    }
};
