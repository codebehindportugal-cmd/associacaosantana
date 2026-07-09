<?php

namespace Database\Seeders;

use App\Models\Cota;
use App\Models\Socio;
use Illuminate\Database\Seeder;

class SocioSeeder extends Seeder
{
    public function run(): void
    {
        $nomes = [
            'António Silva', 'Maria Santos', 'João Ferreira', 'Ana Costa', 'Manuel Pereira',
            'Carla Rodrigues', 'Pedro Martins', 'Sofia Sousa', 'Rui Carvalho', 'Helena Almeida',
            'Miguel Ribeiro', 'Inês Lopes', 'Francisco Gomes', 'Teresa Marques', 'Luís Moreira',
            'Diana Nunes', 'Paulo Mendes', 'Beatriz Teixeira', 'Carlos Correia', 'Marta Neves',
            'Tiago Rocha', 'Cláudia Barros', 'Ricardo Pinto', 'Patrícia Fonseca', 'José Cardoso',
        ];

        foreach ($nomes as $index => $nome) {
            $numero = $index + 1;
            $socio = Socio::firstOrCreate(['numero_socio' => sprintf('S%03d', $numero)], [
                'nome' => $nome,
                'email' => strtolower(str_replace(' ', '.', iconv('UTF-8', 'ASCII//TRANSLIT', $nome))).'@exemplo.pt',
                'telefone' => '91'.str_pad((string) (1000000 + $numero * 4317), 7, '0', STR_PAD_LEFT),
                'morada' => 'Rua da Associação, '.$numero,
                'data_nascimento' => now()->subYears(25 + ($numero % 35))->subDays($numero * 9),
                'data_inscricao' => now()->setDate(2019 + ($numero % 6), ($numero % 12) + 1, min(28, $numero)),
                'estado' => $numero > 22 ? 'inativo' : 'ativo',
            ]);

            if ($numero > 22) {
                continue;
            }

            foreach (range(0, 11) as $offset) {
                $data = now()->startOfMonth()->subMonths($offset);
                $emAtraso = $numero > 15 && $numero <= 22 && $offset < (($numero % 3) + 2);
                Cota::firstOrCreate([
                    'socio_id' => $socio->id,
                    'ano' => $data->year,
                    'mes' => $data->month,
                    'tipo' => 'mensal',
                ], [
                    'valor' => 5,
                    'data_pagamento' => $emAtraso ? null : $data->copy()->addDays(5),
                    'data_vencimento' => $data->copy()->endOfMonth(),
                    'estado' => $emAtraso ? 'em_atraso' : 'pago',
                    'metodo_pagamento' => $emAtraso ? null : 'dinheiro',
                ]);
            }
        }
    }
}
