<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            if (! Schema::hasColumn('reservas', 'observacoes')) {
                $table->text('observacoes')->nullable()->after('sentada_em');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            if (Schema::hasColumn('reservas', 'observacoes')) {
                $table->dropColumn('observacoes');
            }
        });
    }
};
