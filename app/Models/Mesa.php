<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mesa extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero',
        'capacidade',
        'localizacao',
        'estado',
    ];

    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class);
    }
}
