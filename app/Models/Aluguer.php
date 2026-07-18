<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aluguer extends Model
{
    protected $fillable = [
        'nome_cliente',
        'entidade',
        'telefone',
        'email',
        'data_inicio',
        'data_fim',
        'notas',
        'estado',
        'caucao',
        'caucao_devolvida',
        'preco_total',
        'pago',
        'metodo_pagamento',
        'user_id',
    ];

    protected $appends = ['numero_dias'];

    protected $casts = [
        'data_inicio'      => 'date',
        'data_fim'         => 'date',
        'caucao'           => 'float',
        'caucao_devolvida' => 'boolean',
        'preco_total'      => 'float',
        'pago'             => 'boolean',
    ];

    public function opcoes()
    {
        return $this->belongsToMany(AluguerOpcao::class, 'aluguer_aluguer_opcao');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getNumeroDiasAttribute(): int
    {
        return (int) $this->data_inicio->diffInDays($this->data_fim) + 1;
    }
}
