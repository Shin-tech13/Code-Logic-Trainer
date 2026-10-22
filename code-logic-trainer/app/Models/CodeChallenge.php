<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodeChallenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'difficulty',
        'function_name',
        'starter_code',
        'test_cases',
        'badge_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'test_cases' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function completions(): HasMany
    {
        return $this->hasMany(ChallengeCompletion::class);
    }
}
