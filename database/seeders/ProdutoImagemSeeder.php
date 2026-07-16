<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProdutoImagemSeeder extends Seeder
{
    /**
     * Associa imagens SVG aos produtos pelo nome (case-insensitive, partial match).
     * Só actualiza produtos que ainda não têm imagem (imagem IS NULL).
     */
    public function run(): void
    {
        $mapa = [
            // Frango
            '1 frango'            => 'produtos/frango.svg',
            '1/2 frango'          => 'produtos/meio_frango.svg',
            'meio frango'         => 'produtos/meio_frango.svg',
            // Vinho
            'vinho 1'             => 'produtos/vinho.svg',
            'vinho 1/2'           => 'produtos/vinho.svg',
            'vinho branco frisante' => 'produtos/vinho_branco_frisante.svg',
            'vinho tinto'         => 'produtos/vinho.svg',
            // Sangria
            'sangria'             => 'produtos/sangria.svg',
            // Ice tea
            'icetea manga'        => 'produtos/icetea_manga.svg',
            'ice tea manga'       => 'produtos/icetea_manga.svg',
            'icetea limão'        => 'produtos/icetea_limao.svg',
            'icetea limao'        => 'produtos/icetea_limao.svg',
            'ice tea limão'       => 'produtos/icetea_limao.svg',
            'icetea pêssego'      => 'produtos/icetea_pessego.svg',
            'icetea pessego'      => 'produtos/icetea_pessego.svg',
            'ice tea pêssego'     => 'produtos/icetea_pessego.svg',
            // Refrigerantes
            'sumol ananás'        => 'produtos/sumol_ananas.svg',
            'sumol ananas'        => 'produtos/sumol_ananas.svg',
            'sumol laranja'       => 'produtos/sumol_laranja.svg',
            '7up'                 => 'produtos/7up.svg',
            'coca-cola'           => 'produtos/coca_cola.svg',
            'coca cola'           => 'produtos/coca_cola.svg',
            // Alimentação
            'salada'              => 'produtos/salada.svg',
            'batata frita'        => 'produtos/batata_frita.svg',
            'batata'              => 'produtos/batata_frita.svg',
            'pão'                 => 'produtos/pao.svg',
            'pao'                 => 'produtos/pao.svg',
            'sopa'                => 'produtos/sopa.svg',
            // Cerveja
            'sagres preta'        => 'produtos/sagres_preta.svg',
            'sagres média'        => 'produtos/sagres.svg',
            'sagres media'        => 'produtos/sagres.svg',
            'sagres'              => 'produtos/sagres.svg',
        ];

        $produtos = DB::table('produtos')->whereNull('imagem')->get(['id', 'nome']);

        $actualizados = 0;
        foreach ($produtos as $produto) {
            $nomeLower = mb_strtolower($produto->nome);
            $imagemEscolhida = null;

            // Percorre o mapa por ordem (mais específico primeiro)
            foreach ($mapa as $chave => $imagem) {
                if (str_contains($nomeLower, mb_strtolower($chave))) {
                    $imagemEscolhida = $imagem;
                    break;
                }
            }

            if ($imagemEscolhida) {
                DB::table('produtos')->where('id', $produto->id)->update(['imagem' => $imagemEscolhida]);
                $this->command->line("  ✓ [{$produto->id}] {$produto->nome} → {$imagemEscolhida}");
                $actualizados++;
            } else {
                $this->command->line("  ? [{$produto->id}] {$produto->nome} → sem correspondência");
            }
        }

        $this->command->info("Actualizados: {$actualizados} de {$produtos->count()} produtos.");
    }
}
