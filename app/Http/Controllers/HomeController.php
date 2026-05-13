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
                    'poster' => '/images/events/santana-2026.svg',
                    'badge' => 'Próximo evento',
                    'description' => 'Quatro noites de festa com música ao vivo, DJs, restaurante e o famoso frango assado.',
                    'schedule' => [
                        ['day' => '10 Julho', 'label' => 'Sexta-feira', 'items' => ['22h00 Eça Banda', '02h00 DJ Fireball']],
                        ['day' => '11 Julho', 'label' => 'Sábado', 'items' => ['22h00 Banda ATK', '02h00 DJ Fat Mike']],
                        ['day' => '12 Julho', 'label' => 'Domingo', 'items' => ['22h00 JC Power', '23h30 Saul']],
                        ['day' => '13 Julho', 'label' => 'Segunda-feira', 'items' => ['22h00 Nelson Lords']],
                    ],
                ],
                [
                    'title' => 'Afro Beats Tropical Night',
                    'subtitle' => 'Santana',
                    'date' => '22 de Maio',
                    'period' => 'Maio',
                    'location' => 'Associação de Santana',
                    'poster' => '/images/events/afro-tropical-night.svg',
                    'badge' => 'Noite temática',
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
                    'description' => 'Edição anterior das festas de Santana, com música, convívio e serviço de restaurante.',
                    'stats' => ['4 dias', 'Restaurante', 'Música ao vivo'],
                ],
                [
                    'title' => 'Festa de Verão',
                    'date' => 'Agosto 2025',
                    'description' => 'Encontro de verão com bar, música e atividades para a comunidade.',
                    'stats' => ['Bar', 'DJ', 'Comunidade'],
                ],
                [
                    'title' => 'Noite Cultural',
                    'date' => 'Outubro 2025',
                    'description' => 'Serão cultural organizado pela associação com sócios e amigos.',
                    'stats' => ['Cultura', 'Sócios', 'Convívio'],
                ],
            ],
        ]);
    }
}
