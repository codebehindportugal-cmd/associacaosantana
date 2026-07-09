<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE categorias MODIFY secao ENUM('cozinha', 'bebidas', 'acompanhamentos', 'sobremesas', 'servico') NOT NULL");
        DB::statement("ALTER TABLE pedido_items MODIFY secao ENUM('cozinha', 'bebidas', 'acompanhamentos', 'sobremesas', 'servico') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE categorias MODIFY secao ENUM('cozinha', 'bebidas', 'acompanhamentos', 'sobremesas') NOT NULL");
        DB::statement("ALTER TABLE pedido_items MODIFY secao ENUM('cozinha', 'bebidas', 'acompanhamentos', 'sobremesas') NOT NULL");
    }
};
