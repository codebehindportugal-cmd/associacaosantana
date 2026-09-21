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

    /**
     * Nomes dos pontos de venda do bar/café, tirados dos postos POS ativos
     * (localização, ou o nome se não tiver). Mudar os postos no backoffice
     * (Impressoras > Postos POS) muda estes nomes em todo o lado — caixa
     * diária incluída — sem mexer no código de evento para evento.
     */
    public static function pontosBar(): array
    {
        return static::query()
            ->where('ativo', true)
            ->whereIn('tipo', ['bar', 'cafe'])
            ->orderBy('tipo')
            ->orderBy('nome')
            ->get(['nome', 'localizacao'])
            ->map(fn (self $posto) => trim((string) ($posto->localizacao ?: $posto->nome)))
            ->filter()
            ->unique()
            ->values()
            ->all();
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
