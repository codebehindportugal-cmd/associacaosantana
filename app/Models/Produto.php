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
        'disponivel',
    ];

    protected $casts = [
        'disponivel' => 'boolean',
        'preco' => 'decimal:2',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function scopeDisponiveis($query)
    {
        return $query->where('disponivel', true);
    }
}
