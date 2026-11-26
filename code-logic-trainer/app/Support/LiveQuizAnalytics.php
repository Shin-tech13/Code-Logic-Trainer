<?php

namespace App\Support;

use App\Models\QuizSession;
use Illuminate\Support\Collection;

final class LiveQuizAnalytics
{
    public static function build(QuizSession $session): array
    {
        $questions = $session->quiz?->questions ?? collect();
        $participants = $session->participants ?? collect();
        $totalQuestions = $questions->count();
        $participantCount = $participants->count();
        $students = [];

        foreach ($participants as $participant) {
            $summary = self::summarize($questions, $participant->answers ?? []);
            $students[] = [
                'id' => $participant->id,
                'label' => $participant->student?->name ?? 'Student '.$participant->student_id,
                'answered_count' => $summary['answered'],
                'remaining_count' => max(0, $totalQuestions - $summary['answered']),
                'correct_count' => $summary['correct'],
                'incorrect_count' => $summary['answered'] - $summary['correct'],
                'total_count' => $totalQuestions,
                'completion_percent' => $totalQuestions > 0
                    ? round(($summary['answered'] / $totalQuestions) * 100, 1)
                    : 0,
                'accuracy_percent' => $summary['answered'] > 0
                    ? round(($summary['correct'] / $summary['answered']) * 100, 1)
                    : 0,
            ];
        }

        usort($students, static fn (array $left, array $right): int =>
            ($right['answered_count'] <=> $left['answered_count'])
            ?: strnatcasecmp($left['label'], $right['label'])
        );

        $topicGroups = [];
        $difficultyGroups = [];

        foreach ($questions as $question) {
            $topic = self::label($question->getAttribute('topic'))
                ?? self::label($session->topic)
                ?? 'Unassigned';
            $difficulty = self::difficultyLabel($question->getAttribute('difficulty'));

            $topicGroups[$topic][] = $question;
            $difficultyGroups[$difficulty][] = $question;
        }

        $topics = self::groupSummaries($topicGroups, $participants, $participantCount);
        usort($topics, static fn (array $left, array $right): int =>
            strnatcasecmp($left['label'], $right['label'])
        );

        $difficulties = self::groupSummaries($difficultyGroups, $participants, $participantCount);
        $difficultyOrder = ['Easy' => 0, 'Medium' => 1, 'Hard' => 2, 'Unassigned' => 3];
        usort($difficulties, static fn (array $left, array $right): int =>
            ($difficultyOrder[$left['label']] ?? 4) <=> ($difficultyOrder[$right['label']] ?? 4)
            ?: strnatcasecmp($left['label'], $right['label'])
        );

        return [
            'question_count' => $totalQuestions,
            'participant_count' => $participantCount,
            'students' => $students,
            'topics' => $topics,
            'difficulties' => $difficulties,
        ];
    }

    private static function groupSummaries(array $groups, Collection $participants, int $participantCount): array
    {
        $summaries = [];

        foreach ($groups as $label => $questions) {
            $questionCollection = collect($questions);
            $answeredTotal = 0;
            $correctTotal = 0;

            foreach ($participants as $participant) {
                $summary = self::summarize($questionCollection, $participant->answers ?? []);
                $answeredTotal += $summary['answered'];
                $correctTotal += $summary['correct'];
            }

            $totalQuestions = $questionCollection->count();
            $averageAnswered = $participantCount > 0 ? $answeredTotal / $participantCount : 0;
            $averageCorrect = $participantCount > 0 ? $correctTotal / $participantCount : 0;

            $summaries[] = [
                'label' => $label,
                'total_count' => $totalQuestions,
                'average_answered_count' => round($averageAnswered, 1),
                'average_correct_count' => round($averageCorrect, 1),
                'completion_percent' => $totalQuestions > 0
                    ? round(($averageAnswered / $totalQuestions) * 100, 1)
                    : 0,
                'accuracy_percent' => $answeredTotal > 0
                    ? round(($correctTotal / $answeredTotal) * 100, 1)
                    : 0,
            ];
        }

        return $summaries;
    }

    private static function summarize(Collection $questions, array $answers): array
    {
        $answered = 0;
        $correct = 0;

        foreach ($questions as $question) {
            $questionId = (string) $question->getKey();
            if (! array_key_exists($questionId, $answers) && ! array_key_exists((int) $questionId, $answers)) {
                continue;
            }

            $answered++;
            $answer = $answers[$questionId] ?? $answers[(int) $questionId] ?? null;
            if (is_string($answer) && $question->isCorrectAnswer($answer)) {
                $correct++;
            }
        }

        return compact('answered', 'correct');
    }

    private static function label(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private static function difficultyLabel(mixed $value): string
    {
        $label = self::label($value);
        if ($label === null) {
            return 'Unassigned';
        }

        return match (mb_strtolower($label)) {
            'easy' => 'Easy',
            'medium' => 'Medium',
            'hard' => 'Hard',
            default => mb_convert_case($label, MB_CASE_TITLE, 'UTF-8'),
        };
    }
}
