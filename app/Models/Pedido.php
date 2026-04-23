<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;

    protected $fillable = [
        'mesa_id',
        'estado',
        'total',
        'observacoes',
    ];

    public function mesa()
    {
        return $this->belongsTo(Mesa::class);
    }

    public function items()
    {
        return $this->hasMany(PedidoItem::class);
    }
}
