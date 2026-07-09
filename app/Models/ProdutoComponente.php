<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProdutoComponente extends Model
{
    use HasFactory;

    protected $fillable = [
        'produto_id',
        'componente_id',
        'quantidade',
    ];

    protected $casts = [
        'quantidade' => 'decimal:4',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }

    public function componente()
    {
        return $this->belongsTo(Produto::class, 'componente_id');
    }
}
