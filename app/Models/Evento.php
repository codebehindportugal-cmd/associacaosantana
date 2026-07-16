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
        'inscricoes_ativas',
        'inscricoes_limite',
        'inscricoes_opcoes',
        'inscricoes_pede_idades',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'programa' => 'array',
        'destaque' => 'boolean',
        'ordem' => 'integer',
        'inscricoes_ativas' => 'boolean',
        'inscricoes_limite' => 'integer',
        'inscricoes_opcoes' => 'array',
        'inscricoes_pede_idades' => 'boolean',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(EventoMedia::class)->orderBy('ordem')->orderBy('id');
    }

    public function inscricoes(): HasMany
    {
        return $this->hasMany(EventoInscricao::class);
    }

    public function totalPessoasInscritas(): int
    {
        return (int) $this->inscricoes()->sum('num_pessoas');
    }

    public function vagasEsgotadas(): bool
    {
        return $this->inscricoes_limite !== null
            && $this->totalPessoasInscritas() >= $this->inscricoes_limite;
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('estado', 'publicado');
    }
}
