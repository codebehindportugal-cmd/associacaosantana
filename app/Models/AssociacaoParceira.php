<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssociacaoParceira extends Model
{
    use HasFactory;

    protected $table = 'associacoes_parceiras';

    protected $fillable = [
        'nome',
        'sigla',
        'percentagem',
        'responsavel',
        'telefone',
        'email',
        'logo',
        'notas',
        'ativo',
        'ordem',
    ];

    protected $casts = [
        'percentagem' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    public static function ativas()
    {
        return static::where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();
    }

    /**
     * Divide a receita bruta do periodo pelas percentagens acordadas.
     * Cada associacao suporta os seus proprios custos, por isso a base
     * da divisao e a receita bruta e nao o resultado.
     */
    public static function divisao(float $receitaBruta): array
    {
        $associacoes = static::ativas();
        $somaPercentagens = (float) $associacoes->sum('percentagem');

        $linhas = $associacoes->map(fn ($associacao) => [
            'id' => $associacao->id,
            'nome' => $associacao->nome,
            'sigla' => $associacao->sigla,
            'percentagem' => (float) $associacao->percentagem,
            'valor' => round($receitaBruta * ((float) $associacao->percentagem) / 100, 2),
        ])->values();

        $atribuido = (float) $linhas->sum('valor');

        return [
            'receita_bruta' => round($receitaBruta, 2),
            'soma_percentagens' => round($somaPercentagens, 2),
            'percentagens_ok' => abs($somaPercentagens - 100) < 0.01,
            'atribuido' => round($atribuido, 2),
            // Sobra por arredondamento aos centimos, ou valor nao atribuido
            // quando as percentagens nao somam 100%.
            'residuo' => round($receitaBruta - $atribuido, 2),
            'linhas' => $linhas->all(),
        ];
    }
}
