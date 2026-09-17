<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class PosSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'pin',
        'localizacao',
        'impressora_id',
        'impressao_navegador',
        'tipo',
        'ativo',
    ];

    protected $hidden = [
        'pin',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'impressao_navegador' => 'boolean',
        'ultimo_login_em' => 'datetime',
    ];

    /**
     * Impressora do posto. Com varios pontos de pre-pagamento, o talao tem de
     * sair onde a venda foi feita e nao na primeira impressora da seccao.
     */
    public function impressora()
    {
        return $this->belongsTo(Impressora::class);
    }

    public function setPinAttribute(string $value): void
    {
        $this->attributes['pin'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
    }

    public function validarPin(string $pin): bool
    {
        return Hash::check($pin, $this->pin);
    }
}
