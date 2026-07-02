<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaturaCompra extends Model
{
    use HasFactory;

    protected $fillable = [
        'fornecedor',
        'numero',
        'data',
        'total',
    ];

    protected $casts = [
        'data' => 'date',
        'total' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(FaturaCompraItem::class);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
