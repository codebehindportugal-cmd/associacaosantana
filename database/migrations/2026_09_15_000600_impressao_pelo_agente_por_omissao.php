<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A impressao pelo navegador nao serve para taloes termicos: o HTML vai
     * pelo driver, que usa um tamanho de papel fixo e nao recebe o comando de
     * corte (0x1D 0x56 0x00). O papel sai desalinhado e sem cortar.
     *
     * O corte e o alinhamento so existem no caminho ESC/POS, feito pelo agente
     * local. Passa a ser esse o modo por omissao; o navegador fica disponivel
     * como opcao por posto, para quem quiser imprimir numa impressora normal.
     */
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->boolean('impressao_navegador')->default(false)->change();
        });

        DB::table('pos_sessions')->update(['impressao_navegador' => false]);
    }

    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->boolean('impressao_navegador')->default(true)->change();
        });
    }
};
