<?php

use App\Models\Mesa;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $layout = [
            1 => [31, 82, 6, 14], 2 => [39, 82, 6, 14], 3 => [47, 82, 6, 14], 4 => [55, 82, 6, 14],
            5 => [63, 82, 6, 14], 6 => [71, 82, 6, 14], 7 => [79, 82, 6, 14], 8 => [87, 82, 6, 14],

            9 => [31, 58, 6, 11], 10 => [39, 58, 6, 11], 11 => [47, 58, 6, 11], 12 => [55, 58, 6, 11],
            13 => [63, 58, 6, 11], 14 => [71, 58, 6, 11], 15 => [79, 58, 6, 11], 16 => [87, 58, 6, 11],

            17 => [31, 44, 6, 11], 18 => [39, 44, 6, 11], 19 => [47, 44, 6, 11], 20 => [55, 44, 6, 11],
            21 => [63, 44, 6, 11], 22 => [71, 44, 6, 11], 23 => [79, 44, 6, 11], 24 => [87, 44, 6, 11],

            25 => [31, 17, 6, 13], 26 => [39, 17, 6, 13], 27 => [47, 17, 6, 13], 28 => [55, 17, 6, 13],
            29 => [63, 17, 6, 13], 30 => [71, 17, 6, 13], 31 => [79, 17, 6, 13], 32 => [87, 17, 6, 13],

            33 => [90, 61, 5, 10], 34 => [90, 49, 5, 10], 35 => [90, 37, 5, 10], 36 => [90, 82, 5, 12],
            37 => [96, 61, 4, 10], 38 => [96, 49, 4, 10], 39 => [96, 37, 4, 10],

            40 => [12, 70, 7, 11], 41 => [12, 83, 7, 11],
        ];

        foreach ($layout as $numero => [$x, $y, $largura, $altura]) {
            Mesa::where('numero', $numero)
                ->whereNull('mesa_principal_id')
                ->update([
                    'mapa_x' => $x,
                    'mapa_y' => $y,
                    'mapa_largura' => $largura,
                    'mapa_altura' => $altura,
                ]);
        }
    }

    public function down(): void
    {
        foreach (range(1, 41) as $numero) {
            Mesa::where('numero', $numero)
                ->whereNull('mesa_principal_id')
                ->update([
                    'mapa_x' => 1 + ((($numero - 1) % 11) * 9),
                    'mapa_y' => 5 + (intdiv($numero - 1, 11) * 23),
                    'mapa_largura' => 7,
                    'mapa_altura' => 15,
                ]);
        }
    }
};
