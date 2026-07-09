<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $comidaId = DB::table('categorias')->where('nome', 'Comida')->value('id')
            ?: DB::table('categorias')->insertGetId([
                'nome' => 'Comida',
                'secao' => 'comida',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $sobremesasId = DB::table('categorias')->where('nome', 'Sobremesas')->value('id')
            ?: DB::table('categorias')->insertGetId([
                'nome' => 'Sobremesas',
                'secao' => 'sobremesas',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('categorias')->where('id', $comidaId)->update(['secao' => 'comida']);
        DB::table('categorias')->where('id', $sobremesasId)->update(['secao' => 'sobremesas']);

        $categoriasAntigas = DB::table('categorias')
            ->whereIn('nome', ['Acompanhamentos', 'Cozinha', 'Serviço', 'Servico'])
            ->pluck('id');

        if ($categoriasAntigas->isNotEmpty()) {
            DB::table('produtos')->whereIn('categoria_id', $categoriasAntigas)->update(['categoria_id' => $comidaId]);
            DB::table('categorias')->whereIn('id', $categoriasAntigas)->delete();
        }
    }

    public function down(): void
    {
        //
    }
};
