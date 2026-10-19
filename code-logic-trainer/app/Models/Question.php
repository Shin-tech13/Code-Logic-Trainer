<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_session_id',
        'question_text',
        'options',
        'correct_answer',
        'explanation',
    ];

    protected $casts = [
        'options' => 'array', // Automatically casts JSON options column into a PHP array
    ];

    // The quiz session this question belongs to
    public function quizSession()
    {
        return $this->belongsTo(QuizSession::class);
    }
}
