<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1) Talao por evento: cada evento passa a ter o seu modelo de talao,
     *    e escolhe-se qual esta em uso.
     * 2) Talao individual por unidade deixa de estar fixo na seccao "frango"
     *    e passa a ser uma opcao de cada produto, configuravel no backoffice.
     */
    public function up(): void
    {
        Schema::table('talao_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('talao_configs', 'nome')) {
                $table->string('nome')->nullable()->after('id');
            }
            if (! Schema::hasColumn('talao_configs', 'evento_id')) {
                $table->foreignId('evento_id')->nullable()->after('nome')
                    ->constrained('eventos')->nullOnDelete();
            }
            if (! Schema::hasColumn('talao_configs', 'em_uso')) {
                $table->boolean('em_uso')->default(false)->after('ativo');
            }
            if (! Schema::hasColumn('talao_configs', 'instrucoes_individual')) {
                // Impresso em cada talao unitario entregue ao cliente
                $table->text('instrucoes_individual')->nullable()->after('rodape');
            }
        });

        // O modelo que ja existia passa a ser o que esta em uso
        $primeiro = DB::table('talao_configs')->orderBy('id')->first();

        if ($primeiro) {
            DB::table('talao_configs')->where('id', $primeiro->id)->update([
                'nome' => $primeiro->nome ?: ($primeiro->titulo ?: 'Modelo principal'),
                'em_uso' => true,
                'instrucoes_individual' => $primeiro->instrucoes_individual
                    ?: "Entregar este talao no balcao\nObrigado e boa festa!",
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasColumn('produtos', 'talao_individual')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->boolean('talao_individual')->default(false)->after('disponivel_bar');
            });

            // Mantem o comportamento actual: o frango ja saia 1 talao por unidade
            DB::statement("
                UPDATE produtos
                INNER JOIN categorias ON categorias.id = produtos.categoria_id
                SET produtos.talao_individual = 1
                WHERE categorias.secao = 'frango'
            ");
        }
    }

    public function down(): void
    {
        Schema::table('talao_configs', function (Blueprint $table) {
            if (Schema::hasColumn('talao_configs', 'evento_id')) {
                $table->dropConstrainedForeignId('evento_id');
            }

            foreach (['nome', 'em_uso', 'instrucoes_individual'] as $coluna) {
                if (Schema::hasColumn('talao_configs', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });

        if (Schema::hasColumn('produtos', 'talao_individual')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->dropColumn('talao_individual');
            });
        }
    }
};
