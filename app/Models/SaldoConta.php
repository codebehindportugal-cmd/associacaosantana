<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaldoConta extends Model
{
    protected $table = 'saldos_conta';

    protected $fillable = ['conta', 'valor', 'data', 'notas', 'user_id'];

    protected $casts = [
        'data'  => 'date',
        'valor' => 'float',
    ];
}
