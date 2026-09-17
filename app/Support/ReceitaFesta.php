<?php

namespace App\Support;

use App\Models\FestaMovimento;
use App\Models\Pedido;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fonte unica da receita bruta da festa.
 *
 * O ecra Contas da Festa (backoffice) e a pagina partilhada com as outras
 * associacoes usam os dois este calculo, para que ninguem veja numeros
 * diferentes do mesmo evento.
 */
class ReceitaFesta
{
    public static function linhas(string $inicio, string $fim): Collection
    {
        $pedidos = Pedido::query()
            ->whereBetween(DB::raw('DATE(created_at)'), [$inicio, $fim])
            ->where(fn ($query) => $query->where('estado', 'entregue')->orWhere('pago_antecipado', true))
            ->get();

        $automaticas = collect([
            [
                'categoria' => 'restaurante',
                'label' => 'Restaurante',
                'valor' => (float) $pedidos->where('tipo', 'restaurante')->sum('total'),
                'origem' => 'automatico',
            ],
            [
                'categoria' => 'bar',
                'label' => 'Bar',
                'valor' => (float) $pedidos->whereIn('tipo', ['bar_conta', 'bar_prepago'])->sum('total'),
                'origem' => 'automatico',
            ],
            [
                'categoria' => 'doacoes',
                'label' => 'Doacoes',
                'valor' => (float) $pedidos->sum('doacao'),
                'origem' => 'automatico',
            ],
        ]);

        $manuais = FestaMovimento::query()
            ->where('tipo', 'receita')
            ->where(function ($query) use ($inicio, $fim) {
                $query->whereNull('data')->orWhereBetween('data', [$inicio, $fim]);
            })
            ->get()
            ->groupBy('categoria')
            ->map(fn ($grupo, $categoria) => [
                'categoria' => $categoria,
                'label' => static::label($categoria),
                'valor' => (float) $grupo->sum('valor'),
                'origem' => 'manual',
            ])
            ->values();

        return $automaticas->merge($manuais)->values();
    }

    public static function total(string $inicio, string $fim): float
    {
        return (float) static::linhas($inicio, $fim)->sum('valor');
    }

    public static function label(string $categoria): string
    {
        return [
            'restaurante' => 'Restaurante',
            'bar' => 'Bar',
            'bar_manual' => 'Bar (manual)',
            'cafe' => 'Cafe',
            'quermesse' => 'Quermesse',
            'patrocinios' => 'Patrocinios',
            'doacoes' => 'Doacoes',
            'donativos_manuais' => 'Donativos manuais',
        ][$categoria] ?? ucfirst(str_replace('_', ' ', $categoria));
    }
}
