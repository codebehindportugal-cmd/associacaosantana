<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE reservas MODIFY estado ENUM('em_espera','confirmada','sentada','cancelada') NOT NULL DEFAULT 'confirmada'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE reservas MODIFY estado ENUM('confirmada','sentada','cancelada') NOT NULL DEFAULT 'confirmada'");
    }
};
