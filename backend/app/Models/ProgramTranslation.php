<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramTranslation extends Model
{
    protected $fillable = [
        'program_id', 'locale', 'title', 'description',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
