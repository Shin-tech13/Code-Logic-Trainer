<?php

namespace App\Models;

use App\Support\QuestionTimer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'professor_id',
        'title',
        'description',
        'source_file_id',
        'question_count',
        'seconds_per_question',
        'status',
        'quiz_format',
    ];

    public function getSecondsPerQuestionAttribute($value): int
    {
        return QuestionTimer::normalize($value);
    }

    public function setSecondsPerQuestionAttribute($value): void
    {
        $this->attributes['seconds_per_question'] = QuestionTimer::normalize($value);
    }

    public function professor()
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    public function sourceFile()
    {
        return $this->belongsTo(UploadedFile::class, 'source_file_id');
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function quizSessions()
    {
        return $this->hasMany(QuizSession::class);
    }
}
