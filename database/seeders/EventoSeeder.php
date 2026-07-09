<?php

namespace Database\Seeders;

use App\Models\Evento;
use Illuminate\Database\Seeder;

class EventoSeeder extends Seeder
{
    public function run(): void
    {
        Evento::updateOrCreate(
            [
                'titulo' => 'Santana 2026',
                'data_inicio' => '2026-07-10',
            ],
            [
                'subtitulo' => 'Carvalhal Benfeito',
                'data_fim' => '2026-07-13',
                'periodo' => 'Julho 2026',
                'localizacao' => 'Santana, Carvalhal Benfeito',
                'badge' => 'Proximo evento',
                'descricao' => 'Quatro noites de festa com musica ao vivo, DJs, restaurante e o famoso frango assado.',
                'cartaz' => '/images/events/santana-2026-cartaz.png',
                'programa' => [
                    ['day' => '10 Julho', 'label' => 'Sexta-feira', 'items' => ['22h00 Eca Banda', '02h00 DJ Fireball']],
                    ['day' => '11 Julho', 'label' => 'Sabado', 'items' => ['22h00 Banda ATK', '02h00 DJ Fat Mike']],
                    ['day' => '12 Julho', 'label' => 'Domingo', 'items' => ['22h00 JC Power', '23h30 Saul']],
                    ['day' => '13 Julho', 'label' => 'Segunda-feira', 'items' => ['22h00 Nelson Lords']],
                ],
                'estado' => 'publicado',
                'destaque' => true,
                'ordem' => 0,
            ]
        );

        Evento::updateOrCreate(
            [
                'titulo' => 'Afro Beats Tropical Night',
                'data_inicio' => '2026-05-22',
            ],
            [
                'subtitulo' => 'Santana',
                'data_fim' => '2026-05-22',
                'periodo' => 'Maio 2026',
                'localizacao' => 'Associacao de Santana',
                'badge' => 'Noite tematica',
                'descricao' => 'Uma noite tropical dedicada aos ritmos afro, com Foko e Filipexs.',
                'cartaz' => '/images/events/afro-beats-tropical-night-22-maio.jpeg',
                'programa' => [
                    ['day' => '22 Maio', 'label' => '22h00', 'items' => ['Afro Beats Tropical Night', 'Foko', 'Filipexs']],
                ],
                'estado' => 'publicado',
                'destaque' => false,
                'ordem' => 1,
            ]
        );

        $caminhada = Evento::updateOrCreate(
            [
                'titulo' => 'Caminhada da Primavera',
                'data_inicio' => '2026-04-12',
            ],
            [
                'subtitulo' => '35 Aniversario de ARDC Santana',
                'data_fim' => '2026-04-12',
                'periodo' => 'Abril 2026',
                'localizacao' => 'Santana',
                'badge' => 'Evento realizado',
                'descricao' => 'Caminhada da Primavera realizada no aniversario da associacao, com passeio, convivio e almoco.',
                'cartaz' => '/images/events/caminhada-primavera-2026-info.jpeg',
                'programa' => [
                    [
                        'day' => '12 Abril',
                        'label' => 'Programa',
                        'items' => [
                            'Aquecimento com Prof. Zumba Daniela Cortez',
                            'Inicio do passeio as 9h',
                            'Percurso medio/dificil de 10Km',
                            'Missa as 16h em memoria dos socios falecidos',
                        ],
                    ],
                ],
                'estado' => 'publicado',
                'destaque' => false,
                'ordem' => 10,
            ]
        );

        $caminhada->media()->updateOrCreate(
            ['caminho' => '/images/events/caminhada-primavera-2026.jpeg'],
            ['tipo' => 'foto', 'titulo' => 'Cartaz de inscricoes', 'ordem' => 0]
        );

        $caminhada->media()->updateOrCreate(
            ['caminho' => '/images/events/caminhada-primavera-2026-info.jpeg'],
            ['tipo' => 'foto', 'titulo' => 'Programa do evento', 'ordem' => 1]
        );
    }
}
