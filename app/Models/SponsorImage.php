<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorImage extends Model
{
    protected $fillable = ['sponsor_id', 'path', 'ordem'];

    protected $casts = ['ordem' => 'integer'];

    protected $appends = ['url'];

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function getUrlAttribute(): string
    {
        if (str_starts_with($this->path, '/')) {
            return asset(ltrim($this->path, '/'));
        }

        return asset('storage/'.$this->path);
    }
}
