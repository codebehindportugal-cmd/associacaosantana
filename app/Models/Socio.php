<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Socio extends Model
{
    use HasFactory;

    /** A cota e anual e simbolica: 5 EUR por ano. */
    public const VALOR_COTA_ANUAL = 5.0;

    protected $fillable = [
        'numero_socio',
        'nome',
        'email',
        'telefone',
        'morada',
        'data_nascimento',
        'data_inscricao',
        'estado',
    ];

    protected $appends = ['cota_em_dia', 'anos_em_atraso', 'valor_em_divida'];

    protected $casts = [
        'data_inscricao' => 'date',
    ];

    public function cotas()
    {
        return $this->hasMany(Cota::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('estado', 'ativo');
    }

    /** Em atraso = nao tem a cota deste ano paga. */
    public function scopeEmAtraso($query)
    {
        return $query->whereDoesntHave(
            'cotas',
            fn ($cotas) => $cotas->where('estado', 'pago')->where('ano', now()->year),
        );
    }

    public function getCotaEmDiaAttribute(): bool
    {
        return $this->cotas()
            ->where('estado', 'pago')
            ->where('ano', now()->year)
            ->exists();
    }

    /**
     * Anos por pagar, desde o ano de inscricao ate ao ano corrente.
     *
     * Antes contavam-se meses, de quando a cota era mensal. E anual: quem nao
     * paga desde 2024 deve dois anos, nao vinte e quatro meses.
     */
    public function getAnosEmAtrasoAttribute(): int
    {
        $anoAtual = (int) now()->year;
        $inicio = (int) (optional($this->data_inscricao)->year ?: $anoAtual);
        $inicio = min($inicio, $anoAtual);

        $pagos = $this->cotas()
            ->where('estado', 'pago')
            ->pluck('ano')
            ->map(fn ($ano) => (int) $ano)
            ->unique()
            ->all();

        return collect(range($inicio, $anoAtual))
            ->reject(fn ($ano) => in_array($ano, $pagos, true))
            ->count();
    }

    public function getValorEmDividaAttribute(): float
    {
        return $this->anos_em_atraso * self::VALOR_COTA_ANUAL;
    }
}
