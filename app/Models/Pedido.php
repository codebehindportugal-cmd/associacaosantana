<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Pedido extends Model
{
    /** Formas de pagamento no pre-pagamento (POS do bar/cafe). So o dinheiro vai para a gaveta. */
    public const METODOS_PREPAGO = [
        'dinheiro' => 'Dinheiro',
        'mbway' => 'MB WAY',
        'contactless' => 'Contactless',
    ];

    use HasFactory;

    protected $fillable = [
        'mesa_id',
        'cliente_token',
        'user_id',
        'pos_id',
        'operador_nome',
        'nome_reserva',
        'estado',
        'tipo',
        'numero_senha',
        'pago_antecipado',
        'ponto_bar',
        'total',
        'caucao_cobrada',
        'caucao_descontada',
        'valor_recebido',
        'troco',
        'doacao',
        'metodo_pagamento',
        'observacoes',
        'chamado_em',
        'conta_pedida_em',
        'juntar',
        'reimpressoes',
        'anulado_em',
        'anulado_por',
        'motivo_anulacao',
        'valor_devolvido',
    ];

    protected $appends = ['total_calculado'];

    protected $hidden = [
        'cliente_token',
    ];

    protected $casts = [
        'pago_antecipado' => 'boolean',
        'juntar' => 'array',
        'anulado_em' => 'datetime',
        'valor_devolvido' => 'decimal:2',
        'caucao_cobrada'  => 'decimal:2',
        'caucao_descontada' => 'decimal:2',
        'chamado_em'      => 'datetime',
        'conta_pedida_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Pedido $pedido) {
            if (! $pedido->cliente_token) {
                $pedido->cliente_token = (string) Str::uuid();
            }
        });
    }

    public function mesa()
    {
        return $this->belongsTo(Mesa::class);
    }

    public function mesasGrupo()
    {
        return $this->belongsToMany(Mesa::class, 'pedido_mesa_grupos')->withTimestamps();
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
