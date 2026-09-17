<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Importa a lista de sócios (Livro1.xlsx → database/data/socios_livro1.csv).
 *
 * - Sócio novo (nº ainda não existe): é criado, ativo, com a localidade na morada.
 * - Sócio que já existe: não é alterado; só se preenche a morada se estiver vazia.
 *   Se o nome for diferente, fica registado no log para verificar à mão.
 *
 * Corre sozinha no deploy (php artisan migrate) e não duplica nada se correr de novo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ficheiro = base_path('database/data/socios_livro1.csv');
        if (! is_file($ficheiro)) {
            Log::warning('Importação de sócios: ficheiro em falta', ['ficheiro' => $ficheiro]);

            return;
        }

        $handle = fopen($ficheiro, 'r');
        fgetcsv($handle, 0, ';'); // cabeçalho

        $existentes = DB::table('socios')->get(['id', 'numero_socio', 'nome', 'morada'])->keyBy('numero_socio');
        $hoje = now()->toDateString();
        $agora = now();
        $novos = [];
        $diferentes = [];

        while (($linha = fgetcsv($handle, 0, ';')) !== false) {
            [$numero, $nome, $localidade] = array_pad(array_map('trim', $linha), 3, '');
            if ($numero === '' || $nome === '') {
                continue;
            }

            $atual = $existentes->get($numero);

            if (! $atual) {
                $novos[] = [
                    'numero_socio' => $numero,
                    'nome' => $nome,
                    'morada' => $localidade ?: null,
                    'data_inscricao' => $hoje,
                    'estado' => 'ativo',
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];

                continue;
            }

            if (! $atual->morada && $localidade) {
                DB::table('socios')->where('id', $atual->id)->update(['morada' => $localidade, 'updated_at' => $agora]);
            }

            if (mb_strtoupper(trim($atual->nome)) !== mb_strtoupper($nome)) {
                $diferentes[] = "{$numero}: BD=\"{$atual->nome}\" / lista=\"{$nome}\"";
            }
        }

        fclose($handle);

        foreach (array_chunk($novos, 100) as $bloco) {
            DB::table('socios')->insert($bloco);
        }

        Log::info('Importação de sócios (Livro1): '.count($novos).' novos.');
        if ($diferentes) {
            Log::warning('Importação de sócios (Livro1): nº já existente com nome diferente — não alterados', $diferentes);
        }
    }

    public function down(): void
    {
        // Não se apagam sócios (podem já ter cotas associadas).
    }
};
