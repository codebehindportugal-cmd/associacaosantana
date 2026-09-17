<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Com a impressora ligada por USB ao computador do posto, nao e preciso
     * agente nenhum: o proprio POS manda imprimir no fim da venda, pelo
     * navegador, na impressora daquele computador.
     *
     * O valor por omissao e true porque e isso que ja acontecia na pratica —
     * a pagina do talao ja chamava window.print() depois de cada venda. Com
     * este campo deixam de ser criados tambem trabalhos para o agente, que
     * era o que fazia sair o mesmo talao duas vezes quando havia agente a
     * correr.
     *
     * Desligar volta a mandar os taloes pelo agente (impressoras de rede, ou
     * postos em telemovel, que nao tem impressora propria).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('pos_sessions', 'impressao_navegador')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->boolean('impressao_navegador')->default(true)->after('impressora_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_sessions', 'impressao_navegador')) {
            Schema::table('pos_sessions', function (Blueprint $table) {
                $table->dropColumn('impressao_navegador');
            });
        }
    }
};
