<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    protected $fillable = [
        'mesa_id',
        'nome',
        'telefone',
        'data',
        'hora',
        'pessoas',
        'estado',
        'chamada_em',
        'sentada_em',
        'observacoes',
    ];

    protected $casts = [
        'chamada_em' => 'datetime',
        'sentada_em' => 'datetime',
    ];

    public function mesa()
    {
        return $this->belongsTo(Mesa::class);
    }
}
