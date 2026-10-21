<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_session_id',
        'student_id',
        'status',
        'score',
        'joined_at',
        'answers',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'answers' => 'array',
    ];

    public function quizSession()
    {
        return $this->belongsTo(QuizSession::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
