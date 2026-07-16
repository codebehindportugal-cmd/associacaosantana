<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValorExtra extends Model
{
    use HasFactory;

    protected $table = 'valor_extras';

    protected $fillable = [
        'data',
        'tipo',
        'descricao',
        'valor',
        'categoria',
        'observacoes',
        'user_id',
    ];

    protected $casts = [
        'data' => 'date',
        'valor' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
