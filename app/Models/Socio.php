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

    public function cotas()
    {
        return $this->hasMany(Cota::class);
    }
}
