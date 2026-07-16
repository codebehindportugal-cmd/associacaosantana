<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChamadaComissao extends Model
{
    protected $table = 'chamadas_comissao';

    protected $fillable = [
        'operador_nome',
        'local',
        'atendida_por_id',
        'atendida_por_nome',
        'atendida_em',
    ];

    protected $casts = [
        'atendida_em' => 'datetime',
    ];

    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendida_por_id');
    }

    public function scopePendentes($query)
    {
        return $query->whereNull('atendida_em');
    }
}
