<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Miniatura (WebP) de cada foto, usada nas grelhas para o site pesar menos. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('evento_media', 'miniatura')) {
            Schema::table('evento_media', function (Blueprint $table) {
                $table->string('miniatura')->nullable()->after('caminho');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('evento_media', 'miniatura')) {
            Schema::table('evento_media', function (Blueprint $table) {
                $table->dropColumn('miniatura');
            });
        }
    }
};
