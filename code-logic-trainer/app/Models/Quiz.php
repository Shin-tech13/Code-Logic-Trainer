<?php

namespace App\Models;

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
        'status',
    ];

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
