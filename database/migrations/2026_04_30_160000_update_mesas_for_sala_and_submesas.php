<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE mesas MODIFY localizacao ENUM('sala','interior','exterior','bar') DEFAULT 'sala'");

        Schema::table('mesas', function (Blueprint $table) {
            $table->foreignId('mesa_principal_id')->nullable()->after('id')->constrained('mesas')->nullOnDelete();
            $table->string('nome')->nullable()->after('mesa_principal_id');
            $table->unsignedTinyInteger('lugares_inicio')->nullable()->after('capacidade');
            $table->unsignedTinyInteger('lugares_fim')->nullable()->after('lugares_inicio');
            $table->boolean('ativa')->default(true)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            $table->dropForeign(['mesa_principal_id']);
            $table->dropColumn(['mesa_principal_id', 'nome', 'lugares_inicio', 'lugares_fim', 'ativa']);
        });

        DB::statement("ALTER TABLE mesas MODIFY localizacao ENUM('interior','exterior','bar') DEFAULT 'interior'");
    }
};
