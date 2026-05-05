<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mesa extends Model
{
    use HasFactory;

    protected $fillable = [
        'mesa_principal_id',
        'numero',
        'nome',
        'capacidade',
        'lugares_inicio',
        'lugares_fim',
        'localizacao',
        'estado',
        'ativa',
        'mapa_x',
        'mapa_y',
        'mapa_largura',
        'mapa_altura',
    ];

    protected $appends = ['designacao', 'lugares'];

    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class);
    }

    public function mesaPrincipal()
    {
        return $this->belongsTo(Mesa::class, 'mesa_principal_id');
    }

    public function submesas()
    {
        return $this->hasMany(Mesa::class, 'mesa_principal_id')->orderBy('numero');
    }

    public function scopePrincipais($query)
    {
        return $query->whereNull('mesa_principal_id');
    }

    public function scopeAtivas($query)
    {
        return $query->where('ativa', true);
    }

    public function scopeLivres($query)
    {
        return $query->where('estado', 'livre');
    }

    public function getDesignacaoAttribute(): string
    {
        return $this->nome ?: 'Mesa '.$this->numero;
    }

    public function getLugaresAttribute(): ?string
    {
        if (! $this->lugares_inicio || ! $this->lugares_fim) {
            return null;
        }

        return $this->lugares_inicio.'-'.$this->lugares_fim;
    }
}
