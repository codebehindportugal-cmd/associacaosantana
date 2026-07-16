<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoInscricao extends Model
{
    protected $table = 'evento_inscricoes';

    protected $fillable = [
        'evento_id',
        'nome',
        'telefone',
        'email',
        'num_pessoas',
        'opcao',
        'num_criancas',
        'idades_criancas',
        'observacoes',
        'valor_estimado',
        'pagamento_estado',
        'pagamento_order_code',
        'pago_em',
    ];

    protected $casts = [
        'num_pessoas' => 'integer',
        'num_criancas' => 'integer',
        'pago_em' => 'datetime',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }
}
