<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE categorias MODIFY secao ENUM('cozinha', 'bebidas', 'acompanhamentos', 'sobremesas', 'servico', 'comida') NOT NULL");
            DB::statement("ALTER TABLE pedido_items MODIFY secao ENUM('cozinha', 'bebidas', 'acompanhamentos', 'sobremesas', 'servico', 'comida') NOT NULL");
        }

        DB::table('categorias')->whereIn('secao', ['cozinha', 'acompanhamentos', 'servico'])->update(['secao' => 'comida']);
        DB::table('pedido_items')->whereIn('secao', ['cozinha', 'acompanhamentos', 'servico'])->update(['secao' => 'comida']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE categorias MODIFY secao ENUM('bebidas', 'comida', 'sobremesas') NOT NULL");
            DB::statement("ALTER TABLE pedido_items MODIFY secao ENUM('bebidas', 'comida', 'sobremesas') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE categorias MODIFY secao ENUM('cozinha', 'bebidas', 'acompanhamentos', 'sobremesas', 'servico', 'comida') NOT NULL");
            DB::statement("ALTER TABLE pedido_items MODIFY secao ENUM('cozinha', 'bebidas', 'acompanhamentos', 'sobremesas', 'servico', 'comida') NOT NULL");
        }
    }
};
