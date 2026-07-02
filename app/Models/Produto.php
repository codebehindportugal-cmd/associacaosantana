<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    use HasFactory;

    protected $fillable = [
        'categoria_id',
        'nome',
        'preco',
        'stock_atual',
        'disponivel',
        'disponivel_restaurante',
        'disponivel_bar',
    ];

    protected $casts = [
        'disponivel' => 'boolean',
        'disponivel_restaurante' => 'boolean',
        'disponivel_bar' => 'boolean',
        'preco' => 'decimal:2',
        'stock_atual' => 'decimal:3',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function faturaCompraItems()
    {
        return $this->hasMany(FaturaCompraItem::class);
    }

    public function scopeDisponiveis($query)
    {
        return $query->where('disponivel', true);
    }

    public function scopeDisponiveisRestaurante($query)
    {
        return $query->disponiveis()->where('disponivel_restaurante', true);
    }

    public function scopeDisponiveisBar($query)
    {
        return $query->disponiveis()->where('disponivel_bar', true);
    }
}
