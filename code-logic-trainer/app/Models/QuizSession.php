<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'quiz_id',
        'professor_id',
        'language',
        'topic',
        'score',
        'status', // active, completed, waiting, in_progress
        'quiz_code',
        'type', // self_study or live
        'started_at',
        'answers',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'answers' => 'array',
    ];

    // The student taking the quiz (self-study)
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    // The professor hosting a live session
    public function professor()
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    // The professor-created quiz backing a live session
    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    // The questions generated for this specific session
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    // Participants joined to a live session
    public function participants()
    {
        return $this->hasMany(SessionParticipant::class);
    }

    /**
     * Scope helper: only live professor-hosted sessions.
     */
    public function scopeLive($query)
    {
        return $query->where('type', 'live');
    }
}
