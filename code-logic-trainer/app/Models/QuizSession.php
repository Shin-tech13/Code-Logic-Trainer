<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Question;

class QuizSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'language',
        'topic',
        'score',
        'status', // active or completed po
    ];

    // The student taking the quiz
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    // The questions generated for this specific session
    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
