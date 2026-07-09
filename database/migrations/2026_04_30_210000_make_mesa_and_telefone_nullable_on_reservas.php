<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropForeign(['mesa_id']);
            $table->foreignId('mesa_id')->nullable()->change();
            $table->string('telefone')->nullable()->change();
            $table->foreign('mesa_id')->references('id')->on('mesas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropForeign(['mesa_id']);
            $table->foreignId('mesa_id')->nullable(false)->change();
            $table->string('telefone')->nullable(false)->change();
            $table->foreign('mesa_id')->references('id')->on('mesas')->cascadeOnDelete();
        });
    }
};
