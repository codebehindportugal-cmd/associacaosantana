<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PrintJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'impressora_id',
        'printable_type',
        'printable_id',
        'tipo',
        'payload',
        'estado',
        'tentativas',
        'ultimo_erro',
        'reservado_ate',
        'impresso_em',
    ];

    protected $casts = [
        'payload' => 'array',
        'tentativas' => 'integer',
        'reservado_ate' => 'datetime',
        'impresso_em' => 'datetime',
    ];

    public function impressora(): BelongsTo
    {
        return $this->belongsTo(Impressora::class);
    }

    public function printable(): MorphTo
    {
        return $this->morphTo();
    }
}
