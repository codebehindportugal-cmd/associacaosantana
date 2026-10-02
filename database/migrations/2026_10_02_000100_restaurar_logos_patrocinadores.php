<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Repõe os logótipos de patrocinadores que ficaram vazios ao editar e guardar
 * sem trocar o logótipo (o campo vazio era gravado por cima do caminho).
 * Os ficheiros nunca foram apagados do disco; o caminho antigo está no registo
 * de alterações (audit_logs). Só mexe em patrocinadores sem logótipo e só repõe
 * caminhos cujo ficheiro ainda existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sponsors') || ! Schema::hasTable('audit_logs')) {
            return;
        }

        $semLogo = DB::table('sponsors')
            ->where(fn ($q) => $q->whereNull('logotipo')->orWhere('logotipo', ''))
            ->pluck('empresa', 'id');

        foreach ($semLogo as $id => $empresa) {
            $registos = DB::table('audit_logs')
                ->where('auditable_type', 'App\\Models\\Sponsor')
                ->where('auditable_id', $id)
                ->orderByDesc('id')
                ->get(['old_values', 'new_values']);

            $caminho = null;
            foreach ($registos as $registo) {
                foreach ([$registo->old_values, $registo->new_values] as $json) {
                    $valores = json_decode((string) $json, true) ?: [];
                    if (! empty($valores['logotipo'])) {
                        $caminho = $valores['logotipo'];
                        break 2;
                    }
                }
            }

            if (! $caminho) {
                Log::warning("Logótipo do patrocinador #{$id} ({$empresa}) não encontrado no registo de alterações.");
                continue;
            }

            if (str_starts_with($caminho, '/') && ! file_exists(public_path(ltrim($caminho, '/')))) {
                Log::warning("Logótipo do patrocinador #{$id} ({$empresa}) já não existe no disco: {$caminho}");
                continue;
            }

            DB::table('sponsors')->where('id', $id)->update(['logotipo' => $caminho]);
            Log::info("Logótipo do patrocinador #{$id} ({$empresa}) reposto: {$caminho}");
        }
    }

    public function down(): void
    {
        // Não há nada a desfazer: só repõe caminhos que já existiam.
    }
};
