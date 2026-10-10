<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS do bar a trabalhar sem internet: o posto guarda as vendas e envia-as
 * quando houver rede. Cada posto offline tem uma letra nas senhas (B-001)
 * para nunca repetir numeros com os outros; o uuid evita vendas duplicadas
 * quando um envio se repete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->boolean('offline')->default(false)->after('impressao_navegador');
            $table->string('prefixo_senha', 3)->nullable()->after('offline');
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('prefixo_senha', 3)->nullable()->after('numero_senha');
        });

        Schema::table('caucao_devolucoes', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('caucao_devolucoes', fn (Blueprint $table) => $table->dropColumn('uuid'));
        Schema::table('pedidos', fn (Blueprint $table) => $table->dropColumn(['uuid', 'prefixo_senha']));
        Schema::table('pos_sessions', fn (Blueprint $table) => $table->dropColumn(['offline', 'prefixo_senha']));
    }
};
