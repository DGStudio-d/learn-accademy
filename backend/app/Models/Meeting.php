<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany as HasManyRelation;

class Meeting extends Model
{
    protected $fillable = [
        'program_id', 'teacher_id', 'title', 'link', 'starts_at', 'timezone', 'description',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function translations(): HasManyRelation
    {
        return $this->hasMany(MeetingTranslation::class);
    }
}
