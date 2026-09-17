<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Associacoes que partilham um evento com a ARDC Santana.
     * A receita bruta do evento e dividida pelas percentagens aqui definidas;
     * cada associacao suporta os seus proprios custos.
     */
    public function up(): void
    {
        if (! Schema::hasTable('associacoes_parceiras')) {
            Schema::create('associacoes_parceiras', function (Blueprint $table) {
                $table->id();
                $table->string('nome');
                $table->string('sigla', 40)->nullable();
                $table->decimal('percentagem', 5, 2)->default(0);
                $table->string('responsavel')->nullable();
                $table->string('telefone', 30)->nullable();
                $table->string('email')->nullable();
                $table->string('logo')->nullable();
                $table->text('notas')->nullable();
                $table->boolean('ativo')->default(true);
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamps();
            });
        }

        // A associacao anfitria fica com 100% ate as percentagens serem acordadas,
        // para que o ecra existente continue a mostrar os mesmos numeros.
        if (DB::table('associacoes_parceiras')->count() === 0) {
            DB::table('associacoes_parceiras')->insert([
                'nome' => 'ARDC Santana',
                'sigla' => 'ARDCS',
                'percentagem' => 100,
                'ativo' => true,
                'ordem' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('associacoes_parceiras');
    }
};
