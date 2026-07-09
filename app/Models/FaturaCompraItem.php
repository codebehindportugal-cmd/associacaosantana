<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaturaCompraItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'fatura_compra_id',
        'produto_id',
        'quantidade',
        'preco_unitario',
        'total',
        'quantidade_devolvida',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'preco_unitario' => 'decimal:2',
        'total' => 'decimal:2',
        'quantidade_devolvida' => 'decimal:3',
    ];

    public function faturaCompra()
    {
        return $this->belongsTo(FaturaCompra::class);
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}
