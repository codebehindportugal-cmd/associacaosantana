<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoMedia extends Model
{
    use HasFactory;

    protected $table = 'evento_media';

    protected $fillable = [
        'evento_id',
        'tipo',
        'caminho',
        'miniatura',
        'titulo',
        'origem',
        'url_origem',
        'aprovado',
        'enviado_nome',
        'enviado_contacto',
        'ordem',
    ];

    protected $casts = [
        'ordem' => 'integer',
        'aprovado' => 'boolean',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }
}
