<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZonaMapa extends Model
{
    use HasFactory;

    protected $table = 'zona_mapas';
    protected $fillable = [
        'nome',
        'mapa_x',
        'mapa_y',
        'mapa_largura',
        'mapa_altura',
        'tipo',
    ];
}
