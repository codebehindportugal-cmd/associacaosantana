<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Impressora extends Model
{
    use HasFactory;

    /** Agente local liga por TCP ao IP da impressora (porta 9100). */
    public const TIPO_REDE = 'rede';

    /** Agente local (Raspberry/Linux) envia para a impressora USB dele. */
    public const TIPO_USB = 'usb';

    /** O proprio browser envia ESC/POS por WebUSB. Sem agente — Chromebooks. */
    public const TIPO_WEBUSB = 'webusb';

    /** Impressao normal do browser (HTML). Nao corta: so para impressoras normais. */
    public const TIPO_NAVEGADOR = 'navegador';

    public const TIPOS = [
        self::TIPO_REDE => 'Rede — IP e porta, impressa pelo Raspberry',
        self::TIPO_USB => 'USB no Raspberry — ligada ao computador do agente',
        self::TIPO_WEBUSB => 'USB pelo browser (WebUSB) — sem agente',
        self::TIPO_NAVEGADOR => 'Impressao normal do browser — nao corta',
    ];

    protected $fillable = [
        'nome',
        'secao',
        'tipo',
        'host',
        'porta',
        'dispositivo',
        'agente',
        'ativa',
    ];

    protected $casts = [
        'porta' => 'integer',
        'ativa' => 'boolean',
    ];

    public function ehUsb(): bool
    {
        return $this->tipo === self::TIPO_USB;
    }

    /** Impressa pelo agente local (fila print_jobs) ou pelo proprio browser. */
    public function usaAgente(): bool
    {
        return in_array($this->tipo, [self::TIPO_REDE, self::TIPO_USB], true);
    }

    public function ehWebUsb(): bool
    {
        return $this->tipo === self::TIPO_WEBUSB;
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }
}
