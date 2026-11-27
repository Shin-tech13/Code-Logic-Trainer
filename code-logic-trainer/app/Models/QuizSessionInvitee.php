<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizSessionInvitee extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_session_id',
        'user_id',
        'email',
    ];

    public function quizSession()
    {
        return $this->belongsTo(QuizSession::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
