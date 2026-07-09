<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cota extends Model
{
    use HasFactory;

    protected $fillable = [
        'socio_id',
        'ano',
        'mes',
        'tipo',
        'valor',
        'data_pagamento',
        'data_vencimento',
        'estado',
        'metodo_pagamento',
        'observacoes',
    ];

    protected $casts = [
        'data_pagamento' => 'date',
        'data_vencimento' => 'date',
    ];

    public function socio()
    {
        return $this->belongsTo(Socio::class);
    }

    public function scopePendentes($query)
    {
        return $query->where('estado', 'pendente');
    }

    public function scopeEmAtraso($query)
    {
        return $query->where('estado', 'em_atraso');
    }

    public function scopeDoAno($query, int $ano)
    {
        return $query->where('ano', $ano);
    }
}
