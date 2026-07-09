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
        'tipo',
        'ativo',
    ];

    protected $hidden = [
        'pin',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function setPinAttribute(string $value): void
    {
        $this->attributes['pin'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
    }

    public function validarPin(string $pin): bool
    {
        return Hash::check($pin, $this->pin);
    }
}
