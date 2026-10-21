<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_session_id',
        'quiz_id',
        'question_text',
        'options',
        'correct_answer',
        'explanation',
    ];

    protected $casts = [
        'options' => 'array', // Automatically casts JSON options column into a PHP array para maayos ang option access
    ];

    // The quiz session this question belongs to
    public function quizSession()
    {
        return $this->belongsTo(QuizSession::class);
    }

    // The professor-created quiz this question belongs to
    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}
