<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotos enviadas pelo público: ficam pendentes até serem aprovadas no backoffice.
 * As fotos já existentes ficam aprovadas (default true).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('evento_media', 'aprovado')) {
            Schema::table('evento_media', function (Blueprint $table) {
                $table->boolean('aprovado')->default(true)->after('url_origem');
                $table->string('enviado_nome', 120)->nullable()->after('aprovado');
                $table->string('enviado_contacto', 160)->nullable()->after('enviado_nome');
            });
        }

        if (! Schema::hasColumn('eventos', 'fotos_publico_ativo')) {
            Schema::table('eventos', function (Blueprint $table) {
                $table->boolean('fotos_publico_ativo')->default(false)->after('destaque');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('evento_media', 'aprovado')) {
            Schema::table('evento_media', function (Blueprint $table) {
                $table->dropColumn(['aprovado', 'enviado_nome', 'enviado_contacto']);
            });
        }

        if (Schema::hasColumn('eventos', 'fotos_publico_ativo')) {
            Schema::table('eventos', function (Blueprint $table) {
                $table->dropColumn('fotos_publico_ativo');
            });
        }
    }
};
