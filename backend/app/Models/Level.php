<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasMany as HasManyRelation;

class Level extends Model
{
    protected $fillable = [
        'language_id', 'name', 'order',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function translations(): HasManyRelation
    {
        return $this->hasMany(LevelTranslation::class);
    }
}
