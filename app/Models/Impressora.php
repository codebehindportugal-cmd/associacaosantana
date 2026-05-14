<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Impressora extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'secao',
        'host',
        'porta',
        'ativa',
    ];

    protected $casts = [
        'porta' => 'integer',
        'ativa' => 'boolean',
    ];

    public function jobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }
}
