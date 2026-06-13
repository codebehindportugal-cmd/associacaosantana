<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pedidos', 'mesa_id')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->dropForeign(['mesa_id']);
            });

            DB::statement('ALTER TABLE pedidos MODIFY mesa_id BIGINT UNSIGNED NULL');

            Schema::table('pedidos', function (Blueprint $table) {
                $table->foreign('mesa_id')->references('id')->on('mesas')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pedidos', 'mesa_id') || DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropForeign(['mesa_id']);
        });

        DB::statement('ALTER TABLE pedidos MODIFY mesa_id BIGINT UNSIGNED NOT NULL');

        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreign('mesa_id')->references('id')->on('mesas')->cascadeOnDelete();
        });
    }
};
