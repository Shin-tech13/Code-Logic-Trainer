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
        'started_at',
        'last_active_at',
        'submitted_at',
        'answers',
        'tab_switch_count',
        'last_tab_switch_at',
        'last_tab_switch_reason',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'started_at' => 'datetime',
        'last_active_at' => 'datetime',
        'submitted_at' => 'datetime',
        'answers' => 'array',
        'tab_switch_count' => 'integer',
        'last_tab_switch_at' => 'datetime',
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
