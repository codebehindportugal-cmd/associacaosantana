<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SponsorshipRequest extends Model
{
    protected $fillable = [
        'nome',
        'empresa',
        'email',
        'telefone',
        'mensagem',
        'aceita_contacto',
        'estado',
    ];

    protected $casts = [
        'aceita_contacto' => 'boolean',
    ];
}
