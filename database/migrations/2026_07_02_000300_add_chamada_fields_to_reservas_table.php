<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->timestamp('chamada_em')->nullable()->after('estado');
            $table->timestamp('sentada_em')->nullable()->after('chamada_em');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn(['chamada_em', 'sentada_em']);
        });
    }
};
