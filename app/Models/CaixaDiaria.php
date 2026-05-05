<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaixaDiaria extends Model
{
    use HasFactory;

    protected $table = 'caixas_diarias';

    protected $fillable = [
        'data',
        'ponto',
        'fundo_maneio',
        'estado',
        'valor_contado',
        'diferenca',
        'observacoes_fecho',
        'user_id',
        'fechado_user_id',
        'fechado_at',
    ];

    protected $casts = [
        'data' => 'date',
        'fundo_maneio' => 'decimal:2',
        'valor_contado' => 'decimal:2',
        'diferenca' => 'decimal:2',
        'fechado_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fechadoPor()
    {
        return $this->belongsTo(User::class, 'fechado_user_id');
    }
}
