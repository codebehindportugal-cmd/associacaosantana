<?php

/*
|--------------------------------------------------------------------------
| Recibo de quota em formato DL (220 × 110 mm, horizontal)
|--------------------------------------------------------------------------
| Todas as medidas em milímetros, a partir do canto superior esquerdo da
| folha (lado do logótipo = topo). "x" = início do texto, "y" = linha onde
| o texto assenta. Para acertar a impressão toda de uma vez, usar os
| offsets (positivo = para a direita / para baixo) no .env:
|   RECIBO_PAPEL_OFFSET_X=1.5
|   RECIBO_PAPEL_OFFSET_Y=-2
| Folha de teste com a grelha do papel: /pos-cotas/recibo/{cota}/papel?grelha=1
*/

return [
    'papel' => [
        'largura' => 220,
        'altura' => 110,

        'offset_x' => (float) env('RECIBO_PAPEL_OFFSET_X', 0),
        'offset_y' => (float) env('RECIBO_PAPEL_OFFSET_Y', 0),

        'fonte_pt' => 11,

        // true = imprime o formulário completo (logótipo, cabeçalho, rótulos e linhas)
        // em papel branco; false = só os dados, para o papel já pré-impresso
        'imprimir_formulario' => (bool) env('RECIBO_PAPEL_FORMULARIO', true),
        'cor_formulario' => env('RECIBO_PAPEL_COR', '#1E3A8A'),

        'campos' => [
            // Parte de cima (com logótipo)
            'socio_cima'     => ['x' => 53,  'y' => 53.5, 'w' => 25],
            'nome'           => ['x' => 102, 'y' => 53.3, 'w' => 104],
            'euros_cima'     => ['x' => 46,  'y' => 63.7, 'w' => 24, 'alinhar' => 'right'],
            'centimos_cima'  => ['x' => 78,  'y' => 63.7, 'w' => 7],
            'ano_cima'       => ['x' => 102.5, 'y' => 63.7, 'w' => 9],

            // Parte de baixo (destacável)
            'socio_baixo'    => ['x' => 52,  'y' => 85,   'w' => 25],
            'ano_baixo'      => ['x' => 196.5, 'y' => 85, 'w' => 9],
            'euros_baixo'    => ['x' => 46,  'y' => 95.5, 'w' => 26, 'alinhar' => 'right'],
            'centimos_baixo' => ['x' => 79,  'y' => 95.5, 'w' => 8],
        ],
    ],
];
