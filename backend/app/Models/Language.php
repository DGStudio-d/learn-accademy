<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    protected $fillable = [
        'code', 'name', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function levels(): HasMany
    {
        return $this->hasMany(Level::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LanguageTranslation::class);
    }
}
