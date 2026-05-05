<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pedidos MODIFY estado ENUM('pendente', 'preparacao', 'pronto', 'entregue', 'cancelado') NOT NULL DEFAULT 'pendente'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pedidos MODIFY estado ENUM('pendente', 'preparacao', 'pronto', 'entregue') NOT NULL DEFAULT 'pendente'");
    }
};
