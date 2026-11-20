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
        'question_type',
        'correct_answer',
        'accepted_answers',
        'explanation',
        'needs_review',
    ];

    protected $casts = [
        'options' => 'array', // Automatically casts JSON options column into a PHP array para maayos ang option access
        'accepted_answers' => 'array',
        'needs_review' => 'boolean',
    ];

    public function isCorrectAnswer(?string $answer): bool
    {
        if ($answer === null || trim($answer) === '') {
            return false;
        }

        if (($this->question_type ?? 'multiple_choice') !== 'fill_in_the_blank') {
            return trim((string) $this->correct_answer) === trim($answer);
        }

        $acceptedAnswers = array_merge(
            [(string) $this->correct_answer],
            $this->accepted_answers ?? [],
        );
        $normalizedAnswer = self::normalizeBlankAnswer($answer);

        foreach ($acceptedAnswers as $acceptedAnswer) {
            if (self::normalizeBlankAnswer((string) $acceptedAnswer) === $normalizedAnswer) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeBlankAnswer(string $answer): string
    {
        $collapsedWhitespace = preg_replace('/\s+/u', ' ', trim($answer)) ?? trim($answer);

        return mb_strtolower($collapsedWhitespace, 'UTF-8');
    }

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
