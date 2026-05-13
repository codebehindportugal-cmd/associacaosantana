<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'upcomingEvents' => [
                [
                    'title' => 'Santana 2026',
                    'subtitle' => 'Carvalhal Benfeito',
                    'date' => '10, 11, 12 e 13 de Julho',
                    'period' => 'Julho 2026',
                    'location' => 'Santana, Carvalhal Benfeito',
                    'poster' => '/images/events/santana-2026-cartaz.png',
                    'badge' => 'Proximo evento',
                    'description' => 'Quatro noites de festa com musica ao vivo, DJs, restaurante e o famoso frango assado.',
                    'schedule' => [
                        ['day' => '10 Julho', 'label' => 'Sexta-feira', 'items' => ['22h00 Eca Banda', '02h00 DJ Fireball']],
                        ['day' => '11 Julho', 'label' => 'Sabado', 'items' => ['22h00 Banda ATK', '02h00 DJ Fat Mike']],
                        ['day' => '12 Julho', 'label' => 'Domingo', 'items' => ['22h00 JC Power', '23h30 Saul']],
                        ['day' => '13 Julho', 'label' => 'Segunda-feira', 'items' => ['22h00 Nelson Lords']],
                    ],
                ],
                [
                    'title' => 'Afro Beats Tropical Night',
                    'subtitle' => 'Santana',
                    'date' => '22 de Maio',
                    'period' => 'Maio',
                    'location' => 'Associacao de Santana',
                    'poster' => '/images/events/afro-beats-tropical-night-22-maio.jpeg',
                    'badge' => 'Noite tematica',
                    'description' => 'Uma noite tropical dedicada aos ritmos afro, com Foko e Filipexs.',
                    'schedule' => [
                        ['day' => '22 Maio', 'label' => '22h00', 'items' => ['Afro Beats Tropical Night', 'Foko', 'Filipexs']],
                    ],
                ],
            ],
            'pastEvents' => [
                [
                    'title' => 'Santana 2025',
                    'date' => 'Julho 2025',
                    'description' => 'Edicao anterior das festas de Santana, com musica, convivio e servico de restaurante.',
                    'stats' => ['4 dias', 'Restaurante', 'Musica ao vivo'],
                ],
                [
                    'title' => 'Festa de Verao',
                    'date' => 'Agosto 2025',
                    'description' => 'Encontro de verao com bar, musica e atividades para a comunidade.',
                    'stats' => ['Bar', 'DJ', 'Comunidade'],
                ],
                [
                    'title' => 'Noite Cultural',
                    'date' => 'Outubro 2025',
                    'description' => 'Serao cultural organizado pela associacao com socios e amigos.',
                    'stats' => ['Cultura', 'Socios', 'Convivio'],
                ],
            ],
        ]);
    }
}
