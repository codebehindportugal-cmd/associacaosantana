<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Botão com link externo no evento (ex.: inscrições feitas noutro site).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('eventos', 'link_externo_url')) {
            Schema::table('eventos', function (Blueprint $table) {
                $table->string('link_externo_url', 2048)->nullable()->after('facebook_post_url');
                $table->string('link_externo_texto', 80)->nullable()->after('link_externo_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('eventos', 'link_externo_url')) {
            Schema::table('eventos', function (Blueprint $table) {
                $table->dropColumn(['link_externo_url', 'link_externo_texto']);
            });
        }
    }
};
