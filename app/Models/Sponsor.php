<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sponsor extends Model
{
    protected $fillable = [
        'empresa',
        'logotipo',
        'website',
        'descricao',
        'mostrar_no_slider',
        'ativo',
        'ordem',
    ];

    protected $casts = [
        'mostrar_no_slider' => 'boolean',
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(SponsorImage::class)->orderBy('ordem');
    }

    public function getLogoUrlAttribute(): string
    {
        if ($this->logotipo && str_starts_with($this->logotipo, '/')) {
            return asset(ltrim($this->logotipo, '/'));
        }

        return $this->logotipo
            ? asset('storage/'.$this->logotipo)
            : asset('images/santana-logo.png');
    }
}
