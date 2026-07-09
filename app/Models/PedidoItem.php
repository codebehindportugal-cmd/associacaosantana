<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pedido_id',
        'produto_id',
        'quantidade',
        'preco_unitario',
        'estado',
        'secao',
        'prioridade',
        'observacoes',
    ];

    protected $casts = [
        'prioridade' => 'boolean',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }

    public function scopeUrgentes($query)
    {
        return $query->where('prioridade', true)->where('estado', 'pendente');
    }
}
