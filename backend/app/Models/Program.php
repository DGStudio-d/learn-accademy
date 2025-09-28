<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany as HasManyRelation;

class Program extends Model
{
    protected $fillable = [
        'language_id', 'level_id', 'title', 'description',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'program_teacher', 'program_id', 'teacher_id')
            ->withTimestamps();
    }

    public function translations(): HasManyRelation
    {
        return $this->hasMany(ProgramTranslation::class);
    }
}
