<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;

    protected $fillable = [
        'mesa_id',
        'user_id',
        'pos_id',
        'estado',
        'tipo',
        'numero_senha',
        'pago_antecipado',
        'ponto_bar',
        'total',
        'valor_recebido',
        'troco',
        'doacao',
        'metodo_pagamento',
        'observacoes',
    ];

    protected $appends = ['total_calculado'];

    protected $casts = [
        'pago_antecipado' => 'boolean',
    ];

    public function mesa()
    {
        return $this->belongsTo(Mesa::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pos()
    {
        return $this->belongsTo(PosSession::class, 'pos_id');
    }

    public function items()
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function pedidoItems()
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function getTotalCalculadoAttribute(): float
    {
        return (float) $this->items->sum(fn ($item) => $item->preco_unitario * $item->quantidade);
    }

    public function scopeRestaurante($query)
    {
        return $query->where('tipo', 'restaurante');
    }

    public function scopeBarConta($query)
    {
        return $query->where('tipo', 'bar_conta');
    }

    public function scopeBarPrepago($query)
    {
        return $query->where('tipo', 'bar_prepago');
    }
}
