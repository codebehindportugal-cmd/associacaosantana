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
        'imagem',
        'preco',
        'caucao',
        'custo_compra_unitario',
        'unidade_compra',
        'custo_preparacao_unitario',
        'stock_atual',
        'gerir_stock',
        'disponivel',
        'disponivel_restaurante',
        'disponivel_bar',
        'talao_individual',
    ];

    protected $casts = [
        'gerir_stock' => 'boolean',
        'disponivel' => 'boolean',
        'disponivel_restaurante' => 'boolean',
        'disponivel_bar' => 'boolean',
        'talao_individual' => 'boolean',
        'preco' => 'decimal:2',
        'caucao' => 'decimal:2',
        'custo_compra_unitario' => 'decimal:4',
        'custo_preparacao_unitario' => 'decimal:4',
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

    public function componentes()
    {
        return $this->hasMany(ProdutoComponente::class);
    }

    public function usadoEm()
    {
        return $this->hasMany(ProdutoComponente::class, 'componente_id');
    }

    public function custoUnitarioEstimado(): float
    {
        $custoBase = $this->relationLoaded('componentes') && $this->componentes->isNotEmpty()
            ? $this->componentes->sum(fn (ProdutoComponente $componente) => (float) $componente->quantidade * (float) ($componente->componente?->custo_compra_unitario ?? 0))
            : (float) $this->custo_compra_unitario;

        return round($custoBase + (float) $this->custo_preparacao_unitario, 4);
    }

    public function margemUnitarioEstimado(): float
    {
        return round((float) $this->preco - $this->custoUnitarioEstimado(), 4);
    }

    public function scopeDisponiveis($query)
    {
        return $query->where('disponivel', true)->comStock();
    }

    /** Produtos que gerem stock so aparecem enquanto houver stock. */
    public function scopeComStock($query)
    {
        return $query->where(fn ($q) => $q->where('gerir_stock', false)->orWhere('stock_atual', '>', 0));
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
