<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evento extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'subtitulo',
        'data_inicio',
        'data_fim',
        'periodo',
        'localizacao',
        'badge',
        'descricao',
        'cartaz',
        'facebook_post_url',
        'programa',
        'estado',
        'destaque',
        'ordem',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'programa' => 'array',
        'destaque' => 'boolean',
        'ordem' => 'integer',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(EventoMedia::class)->orderBy('ordem')->orderBy('id');
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('estado', 'publicado');
    }
}
