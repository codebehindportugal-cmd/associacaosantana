<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AluguerOpcao extends Model
{
    protected $table = 'aluguer_opcoes';

    protected $fillable = [
        'nome',
        'descricao',
        'preco_extra',
        'ativo',
        'ordem',
    ];

    protected $casts = [
        'ativo'       => 'boolean',
        'preco_extra' => 'float',
        'ordem'       => 'integer',
    ];

    public function alugueres()
    {
        return $this->belongsToMany(Aluguer::class, 'aluguer_aluguer_opcao');
    }
}
