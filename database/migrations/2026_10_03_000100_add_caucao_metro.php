<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caucao do metro (estrutura onde vao os copos).
 *
 * - produtos.caucao: valor cobrado a parte por unidade (ex.: Metro 12 € + 5 €).
 * - pedidos.caucao_cobrada: caucao recebida nessa venda (nao e receita).
 * - pedidos.caucao_descontada: metros devolvidos trocados por bebidas nessa venda.
 * - caucao_devolucoes: metros devolvidos com o dinheiro devolvido ao cliente.
 *
 * Aditiva: nao mexe em dados existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('produtos', 'caucao')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->decimal('caucao', 8, 2)->default(0)->after('preco');
            });
        }

        Schema::table('pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos', 'caucao_cobrada')) {
                $table->decimal('caucao_cobrada', 10, 2)->default(0)->after('total');
            }
            if (! Schema::hasColumn('pedidos', 'caucao_descontada')) {
                $table->decimal('caucao_descontada', 10, 2)->default(0)->after('caucao_cobrada');
            }
        });

        if (! Schema::hasTable('caucao_devolucoes')) {
            Schema::create('caucao_devolucoes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
                $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
                $table->unsignedBigInteger('pos_id')->nullable();
                $table->string('operador_nome')->nullable();
                $table->string('ponto', 80)->nullable();
                // dinheiro = 5 € devolvidos; bebidas = descontado numa senha (pedido_id)
                $table->string('modo', 20)->default('dinheiro');
                $table->unsignedInteger('quantidade')->default(1);
                $table->decimal('valor_unitario', 8, 2);
                $table->decimal('valor_total', 10, 2);
                $table->timestamps();

                $table->index(['ponto', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caucao_devolucoes');

        Schema::table('pedidos', function (Blueprint $table) {
            foreach (['caucao_descontada', 'caucao_cobrada'] as $coluna) {
                if (Schema::hasColumn('pedidos', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });

        if (Schema::hasColumn('produtos', 'caucao')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->dropColumn('caucao');
            });
        }
    }
};
