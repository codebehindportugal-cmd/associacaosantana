<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimentoFinanceiro extends Model
{
    protected $table = 'movimentos_financeiros';

    protected $fillable = [
        'tipo',
        'descricao',
        'valor',
        'data',
        'categoria',
        'conta',
        'referencia',
        'user_id',
        'notas',
    ];

    protected $casts = [
        'data'  => 'date',
        'valor' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
