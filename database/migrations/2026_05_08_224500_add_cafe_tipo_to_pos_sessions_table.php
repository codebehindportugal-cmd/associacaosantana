<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pos_sessions MODIFY tipo ENUM('bar', 'cafe', 'restaurante', 'cotas') NOT NULL DEFAULT 'bar'");
    }

    public function down(): void
    {
        DB::table('pos_sessions')->where('tipo', 'cafe')->update(['tipo' => 'bar']);
        DB::statement("ALTER TABLE pos_sessions MODIFY tipo ENUM('bar', 'restaurante', 'cotas') NOT NULL DEFAULT 'bar'");
    }
};
