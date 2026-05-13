<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE mesas MODIFY localizacao ENUM('sala','interior','exterior','bar') DEFAULT 'sala'");
        }

        Schema::table('mesas', function (Blueprint $table) {
            if (! Schema::hasColumn('mesas', 'mesa_principal_id')) {
                $table->foreignId('mesa_principal_id')->nullable()->after('id')->constrained('mesas')->nullOnDelete();
            }

            if (! Schema::hasColumn('mesas', 'nome')) {
                $table->string('nome')->nullable()->after('mesa_principal_id');
            }

            if (! Schema::hasColumn('mesas', 'lugares_inicio')) {
                $table->unsignedTinyInteger('lugares_inicio')->nullable()->after('capacidade');
            }

            if (! Schema::hasColumn('mesas', 'lugares_fim')) {
                $table->unsignedTinyInteger('lugares_fim')->nullable()->after('lugares_inicio');
            }

            if (! Schema::hasColumn('mesas', 'ativa')) {
                $table->boolean('ativa')->default(true)->after('estado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            $table->dropForeign(['mesa_principal_id']);
            $table->dropColumn(['mesa_principal_id', 'nome', 'lugares_inicio', 'lugares_fim', 'ativa']);
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE mesas MODIFY localizacao ENUM('interior','exterior','bar') DEFAULT 'interior'");
        }
    }
};
