<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code_challenge_id',
        'passed_tests',
        'total_tests',
        'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(CodeChallenge::class, 'code_challenge_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
