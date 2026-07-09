<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Socio extends Model
{
    use HasFactory;

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

    protected $appends = ['cota_em_dia', 'meses_em_atraso', 'valor_em_divida'];

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

    public function scopeEmAtraso($query)
    {
        return $query->where(function ($query) {
            $query->whereHas('cotas', fn ($cotas) => $cotas->where('estado', 'em_atraso'))
                ->orWhereDoesntHave('cotas', fn ($cotas) => $cotas->where('estado', 'pago')->where('ano', now()->year));
        });
    }

    public function getCotaEmDiaAttribute(): bool
    {
        return $this->cotas()
            ->where('estado', 'pago')
            ->where(function ($query) {
                $query->where(function ($mensal) {
                    $mensal->where('tipo', 'mensal')
                        ->where('ano', now()->year)
                        ->where('mes', now()->month);
                })->orWhere(function ($anual) {
                    $anual->where('tipo', 'anual')->where('ano', now()->year);
                });
            })
            ->exists();
    }

    public function getMesesEmAtrasoAttribute(): int
    {
        if ($this->cota_em_dia) {
            return 0;
        }

        $anoAtual = now()->year;
        $mesAtual = now()->month;
        $pagos = $this->cotas()
            ->where('estado', 'pago')
            ->where('ano', $anoAtual)
            ->pluck('mes')
            ->filter()
            ->map(fn ($mes) => (int) $mes)
            ->all();

        $inicio = max(1, (int) optional($this->data_inscricao)->month ?: 1);

        return collect(range($inicio, $mesAtual))
            ->reject(fn ($mes) => in_array($mes, $pagos, true))
            ->count();
    }

    public function getValorEmDividaAttribute(): float
    {
        return $this->meses_em_atraso * 5.0;
    }
}
