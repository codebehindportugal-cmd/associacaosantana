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
        'inscricoes_preco',
        'inscricoes_preco_crianca',
        'inscricoes_idade_crianca',
        'inscricoes_pagamento_online',
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
        'inscricoes_preco' => 'decimal:2',
        'inscricoes_preco_crianca' => 'decimal:2',
        'inscricoes_idade_crianca' => 'integer',
        'inscricoes_pagamento_online' => 'boolean',
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

    /**
     * Opções normalizadas: aceita strings antigas ("Só caminhar")
     * e objetos novos ({nome, preco}).
     */
    public function opcoesInscricao(): array
    {
        return collect($this->inscricoes_opcoes ?? [])
            ->map(function ($opcao) {
                if (is_array($opcao)) {
                    return [
                        'nome' => (string) ($opcao['nome'] ?? ''),
                        'preco' => isset($opcao['preco']) && $opcao['preco'] !== null ? (float) $opcao['preco'] : null,
                    ];
                }

                return ['nome' => (string) $opcao, 'preco' => null];
            })
            ->filter(fn ($opcao) => $opcao['nome'] !== '')
            ->values()
            ->all();
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('estado', 'publicado');
    }
}
