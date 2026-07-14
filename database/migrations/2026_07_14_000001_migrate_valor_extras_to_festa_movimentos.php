<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $extras = DB::table('valor_extras')->get();

        foreach ($extras as $v) {
            // Skip if already migrated (same descricao + valor + data already in festa_movimentos)
            $exists = DB::table('festa_movimentos')
                ->where('descricao', $v->descricao)
                ->where('valor', $v->valor)
                ->where('data', $v->data)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('festa_movimentos')->insert([
                'tipo'        => $v->tipo === 'despesa' ? 'custo' : 'receita',
                'categoria'   => $v->categoria ?? 'outros',
                'descricao'   => $v->descricao,
                'data'        => $v->data,
                'valor'       => $v->valor,
                'observacoes' => $v->observacoes,
                'created_at'  => $v->created_at,
                'updated_at'  => $v->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        // Cannot safely reverse without knowing which records were inserted by this migration
        // Data is preserved in valor_extras table
    }
};
