<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reserva extends Model
{
    use HasFactory;

    protected $fillable = [
        'mesa_id',
        'nome',
        'telefone',
        'data',
        'hora',
        'pessoas',
        'estado',
        'chamada_em',
        'sentada_em',
        'observacoes',
        'mesa_atribuida',
        'token',
        'push_subscription',
    ];

    protected $casts = [
        'chamada_em'        => 'datetime',
        'sentada_em'        => 'datetime',
        'push_subscription' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reserva) {
            if (empty($reserva->token)) {
                do {
                    $token = Str::random(12);
                } while (self::where('token', $token)->exists());

                $reserva->token = $token;
            }
        });
    }

    public function mesa()
    {
        return $this->belongsTo(Mesa::class);
    }

    public function urlPublica(): string
    {
        return route('reserva.publica', ['token' => $this->token]);
    }

    public function temPushSubscription(): bool
    {
        return ! empty($this->push_subscription);
    }
}
