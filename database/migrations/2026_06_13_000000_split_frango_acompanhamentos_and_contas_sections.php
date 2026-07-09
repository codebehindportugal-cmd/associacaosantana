<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE categorias MODIFY secao ENUM('bebidas', 'frango', 'acompanhamentos', 'comida', 'sobremesas') NOT NULL");
            DB::statement("ALTER TABLE pedido_items MODIFY secao ENUM('bebidas', 'frango', 'acompanhamentos', 'comida', 'sobremesas') NOT NULL");
        }

        $frangoId = $this->categoria('Frango', 'frango');
        $acompanhamentosId = $this->categoria('Acompanhamentos', 'acompanhamentos');

        $frangoProdutoIds = DB::table('produtos')
            ->where('nome', 'like', '%Frango%')
            ->pluck('id');

        $acompanhamentoProdutoIds = DB::table('produtos')
            ->whereIn('nome', ['Batata frita', 'Salada', 'Pao'])
            ->pluck('id');

        DB::table('produtos')
            ->whereIn('id', $frangoProdutoIds)
            ->update(['categoria_id' => $frangoId]);

        DB::table('pedido_items')
            ->whereIn('produto_id', $frangoProdutoIds)
            ->update(['secao' => 'frango']);

        DB::table('produtos')
            ->whereIn('id', $acompanhamentoProdutoIds)
            ->update(['categoria_id' => $acompanhamentosId]);

        DB::table('pedido_items')
            ->whereIn('produto_id', $acompanhamentoProdutoIds)
            ->update(['secao' => 'acompanhamentos']);
    }

    public function down(): void
    {
        DB::table('pedido_items')->whereIn('secao', ['frango', 'acompanhamentos'])->update(['secao' => 'comida']);
        DB::table('categorias')->whereIn('secao', ['frango', 'acompanhamentos'])->update(['secao' => 'comida']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE categorias MODIFY secao ENUM('bebidas', 'comida', 'sobremesas') NOT NULL");
            DB::statement("ALTER TABLE pedido_items MODIFY secao ENUM('bebidas', 'comida', 'sobremesas') NOT NULL");
        }
    }

    private function categoria(string $nome, string $secao): int
    {
        $existente = DB::table('categorias')->where('nome', $nome)->first();

        if ($existente) {
            DB::table('categorias')->where('id', $existente->id)->update([
                'secao' => $secao,
                'updated_at' => now(),
            ]);

            return (int) $existente->id;
        }

        return (int) DB::table('categorias')->insertGetId([
            'nome' => $nome,
            'secao' => $secao,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
