<?php

namespace App\Models;

use App\Support\QuestionTimer;
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
        'seconds_per_question',
        'status', // active, waiting, in progress, completed
        'quiz_code',
        'type', // self_study or live
        'quiz_format',
        'source_file_id',
        'started_at',
        'answers',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'answers' => 'array',
    ];

    public function getSecondsPerQuestionAttribute($value): int
    {
        return QuestionTimer::normalize($value);
    }

    public function setSecondsPerQuestionAttribute($value): void
    {
        $this->attributes['seconds_per_question'] = QuestionTimer::normalize($value);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function professor()
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function sourceFile()
    {
        return $this->belongsTo(UploadedFile::class, 'source_file_id');
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function participants()
    {
        return $this->hasMany(SessionParticipant::class);
    }

    public function scopeLive($query)
    {
        return $query->where('type', 'live');
    }
}
