<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelTranslation extends Model
{
    protected $fillable = [
        'level_id', 'locale', 'name',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}
