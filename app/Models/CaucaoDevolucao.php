<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Metro devolvido. modo "dinheiro": a caucao saiu da gaveta.
 * modo "bebidas": foi descontada na senha indicada em pedido_id.
 */
class CaucaoDevolucao extends Model
{
    protected $table = 'caucao_devolucoes';

    protected $fillable = [
        'uuid',
        'produto_id',
        'pedido_id',
        'pos_id',
        'operador_nome',
        'ponto',
        'modo',
        'quantidade',
        'valor_unitario',
        'valor_total',
    ];

    protected $casts = [
        'valor_unitario' => 'decimal:2',
        'valor_total' => 'decimal:2',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }
}
