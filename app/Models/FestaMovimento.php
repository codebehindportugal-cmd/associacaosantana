<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FestaMovimento extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipo',
        'categoria',
        'descricao',
        'data',
        'valor',
        'observacoes',
    ];

    protected $casts = [
        'data' => 'date',
        'valor' => 'decimal:2',
    ];
}
