<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pos_sessions', 'ultimo_operador')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->string('ultimo_operador')->nullable()->after('ativo');
                $table->timestamp('ultimo_login_em')->nullable()->after('ultimo_operador');
            });
        }

        // PIN da comissão para o login POS (default: 1234 — alterar no painel)
        DB::table('configuracoes')->updateOrInsert(
            ['chave' => 'comissao_pin'],
            [
                'valor'      => Hash::make('1234'),
                'descricao'  => 'PIN de acesso da comissao no login POS',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_sessions', 'ultimo_operador')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->dropColumn(['ultimo_operador', 'ultimo_login_em']);
            });
        }

        DB::table('configuracoes')->where('chave', 'comissao_pin')->delete();
    }
};
