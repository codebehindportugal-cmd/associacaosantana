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
    ];

    public function socio()
    {
        return $this->belongsTo(Socio::class);
    }
}
