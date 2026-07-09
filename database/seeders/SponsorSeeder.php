<?php

namespace Database\Seeders;

use App\Models\Sponsor;
use Illuminate\Database\Seeder;

class SponsorSeeder extends Seeder
{
    public function run(): void
    {
        $patrocinadores = [
            [
                'empresa' => 'Patrocinador Exemplo A',
                'logotipo' => '/images/sponsors/placeholder-patrocinador-a.svg',
                'website' => 'https://example.com',
                'descricao' => 'Patrocinador de exemplo — substitua pelo real',
                'mostrar_no_slider' => true,
                'ativo' => true,
                'ordem' => 1,
            ],
            [
                'empresa' => 'Patrocinador Exemplo B',
                'logotipo' => '/images/sponsors/placeholder-patrocinador-b.svg',
                'website' => 'https://example.com',
                'descricao' => 'Patrocinador de exemplo — substitua pelo real',
                'mostrar_no_slider' => true,
                'ativo' => true,
                'ordem' => 2,
            ],
            [
                'empresa' => 'Patrocinador Exemplo C',
                'logotipo' => '/images/sponsors/placeholder-patrocinador-c.svg',
                'website' => null,
                'descricao' => 'Patrocinador de exemplo — substitua pelo real',
                'mostrar_no_slider' => true,
                'ativo' => true,
                'ordem' => 3,
            ],
        ];

        foreach ($patrocinadores as $dados) {
            Sponsor::firstOrCreate(
                ['empresa' => $dados['empresa']],
                $dados,
            );
        }
    }
}
