<?php

namespace App\Support;

use App\Models\Question;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use Illuminate\Support\Collection;

final class LiveQuizAnalytics
{
    public const DISCONNECTED_AFTER_SECONDS = 120;

    public const QUESTION_REVIEW_ACCURACY_THRESHOLD = 60;

    public static function build(QuizSession $session): array
    {
        $questions = $session->quiz?->questions ?? collect();
        $participants = $session->participants ?? collect();
        $participantSummaries = $participants
            ->map(fn (SessionParticipant $participant) => self::participantSummary($session, $participant, $questions))
            ->values();
        $questionSummaries = self::questionSummaries($questions, $participants);
        $responseCount = (int) $participantSummaries->sum('answered_count');
        $correctCount = (int) $participantSummaries->sum('correct_count');
        $completedCount = $participantSummaries->where('status', 'Submitted')->count();
        $participantCount = $participants->count();
        $possibleResponses = $participantCount * $questions->count();

        return [
            'question_count' => $questions->count(),
            'participant_count' => $participantCount,
            'students' => $participantSummaries
                ->sortBy(fn (array $participant) => mb_strtolower($participant['name']))
                ->values()
                ->all(),
            'questions' => $questionSummaries,
            'topics' => self::groupSummaries(self::questionGroups($questions, $session, 'topic'), $participants, 'topic'),
            'difficulties' => self::groupSummaries(self::questionGroups($questions, $session, 'difficulty'), $participants, 'difficulty'),
            'summary' => [
                'participant_count' => $participantCount,
                'completed_count' => $completedCount,
                'completion_rate_percent' => $participantCount > 0
                    ? round(($completedCount / $participantCount) * 100, 1)
                    : 0,
                'answered_count' => $responseCount,
                'possible_response_count' => $possibleResponses,
                'correct_count' => $correctCount,
                'accuracy_percent' => $responseCount > 0
                    ? round(($correctCount / $responseCount) * 100, 1)
                    : 0,
                'questions_needing_review' => collect($questionSummaries)
                    ->where('response_count', '>', 0)
                    ->where('accuracy_percent', '<', self::QUESTION_REVIEW_ACCURACY_THRESHOLD)
                    ->count(),
            ],
        ];
    }

    public static function participantSummary(
        QuizSession $session,
        SessionParticipant $participant,
        Collection $questions
    ): array {
        $answers = is_array($participant->answers) ? $participant->answers : [];
        $metrics = self::summarizeQuestions($questions, $answers);
        $submitted = $participant->submitted_at !== null || $participant->status === 'completed';
        $started = $participant->started_at !== null
            || in_array($participant->status, ['in_progress', 'completed'], true)
            || $metrics['answered_count'] > 0;
        $lastActivity = $participant->last_active_at
            ?? $participant->updated_at
            ?? $participant->joined_at;
        $status = self::status($session, $started, $submitted, $lastActivity);

        return [
            'id' => $participant->id,
            'name' => $participant->student?->name ?? 'Student '.$participant->student_id,
            'label' => $participant->student?->name ?? 'Student '.$participant->student_id,
            'status' => $status,
            'started' => $started,
            'status_key' => match ($status) {
                'In progress' => 'in_progress',
                'Submitted' => 'submitted',
                'Disconnected' => 'disconnected',
                default => 'waiting',
            },
            'answered_count' => $metrics['answered_count'],
            'remaining_count' => max(0, $questions->count() - $metrics['answered_count']),
            'question_count' => $questions->count(),
            'total_count' => $questions->count(),
            'correct_count' => $metrics['correct_count'],
            'incorrect_count' => $metrics['incorrect_count'],
            'unanswered_count' => max(0, $questions->count() - $metrics['answered_count']),
            'accuracy_percent' => $metrics['accuracy_percent'],
            'score' => $submitted ? $metrics['correct_count'] : null,
            'score_label' => $submitted ? $metrics['correct_count'].' / '.$questions->count() : null,
            'joined_at' => self::iso($participant->joined_at),
            'started_at' => self::iso($participant->started_at),
            'last_activity_at' => self::iso($lastActivity),
            'submitted_at' => self::iso($participant->submitted_at),
            'updated_at' => self::iso($participant->updated_at),
            'tab_switch_count' => (int) $participant->tab_switch_count,
            'last_tab_switch_at' => self::iso($participant->last_tab_switch_at),
            'last_tab_switch_reason' => $participant->last_tab_switch_reason,
        ];
    }

    public static function participantQuestionRows(SessionParticipant $participant, Collection $questions): array
    {
        $answers = is_array($participant->answers) ? $participant->answers : [];

        return $questions->values()->map(function (Question $question, int $index) use ($answers): array {
            $answer = self::answerFor($answers, $question);
            $answered = is_string($answer) && trim($answer) !== '';
            $isCorrect = $answered && $question->isCorrectAnswer($answer);

            return [
                'number' => $index + 1,
                'question' => $question->question_text,
                'status' => ! $answered ? 'Unanswered' : ($isCorrect ? 'Correct' : 'Incorrect'),
            ];
        })->all();
    }

    private static function status(
        QuizSession $session,
        bool $started,
        bool $submitted,
        mixed $lastActivity
    ): string {
        if ($submitted) {
            return 'Submitted';
        }

        if ($session->status === 'completed') {
            return 'Disconnected';
        }

        if (! $started || $session->status === 'waiting') {
            return 'Waiting';
        }

        if ($session->status !== 'in_progress'
            || $lastActivity === null
            || $lastActivity->lt(now()->subSeconds(self::DISCONNECTED_AFTER_SECONDS))) {
            return 'Disconnected';
        }

        return 'In progress';
    }

    private static function questionSummaries(Collection $questions, Collection $participants): array
    {
        return $questions->values()->map(function (Question $question, int $index) use ($participants): array {
            $responseCount = 0;
            $correctCount = 0;

            foreach ($participants as $participant) {
                $answers = is_array($participant->answers) ? $participant->answers : [];
                $answer = self::answerFor($answers, $question);
                if (! is_string($answer) || trim($answer) === '') {
                    continue;
                }

                $responseCount++;
                if ($question->isCorrectAnswer($answer)) {
                    $correctCount++;
                }
            }

            $incorrectCount = $responseCount - $correctCount;

            return [
                'id' => $question->id,
                'number' => $index + 1,
                'question' => $question->question_text,
                'response_count' => $responseCount,
                'participant_count' => $participants->count(),
                'correct_count' => $correctCount,
                'incorrect_count' => $incorrectCount,
                'unanswered_count' => max(0, $participants->count() - $responseCount),
                'accuracy_percent' => $responseCount > 0
                    ? round(($correctCount / $responseCount) * 100, 1)
                    : 0,
            ];
        })->all();
    }

    private static function questionGroups(Collection $questions, QuizSession $session, string $dimension): array
    {
        $groups = [];

        foreach ($questions as $question) {
            $label = $dimension === 'topic'
                ? (self::label($question->getAttribute('topic')) ?? self::label($session->topic) ?? 'Unassigned')
                : self::difficultyLabel($question->getAttribute('difficulty'));
            $groups[$label][] = $question;
        }

        return $groups;
    }

    private static function groupSummaries(array $groups, Collection $participants, string $dimension): array
    {
        $summaries = [];

        foreach ($groups as $label => $groupQuestions) {
            $questions = collect($groupQuestions);
            $responseCount = 0;
            $correctCount = 0;

            foreach ($participants as $participant) {
                $answers = is_array($participant->answers) ? $participant->answers : [];
                $metrics = self::summarizeQuestions($questions, $answers);
                $responseCount += $metrics['answered_count'];
                $correctCount += $metrics['correct_count'];
            }

            $possibleResponses = $questions->count() * $participants->count();
            $incorrectCount = $responseCount - $correctCount;
            $unansweredCount = max(0, $possibleResponses - $responseCount);
            $participantCount = $participants->count();

            $summaries[] = [
                'label' => $label,
                'total_count' => $questions->count(),
                'possible_response_count' => $possibleResponses,
                'response_count' => $responseCount,
                'correct_count' => $correctCount,
                'incorrect_count' => $incorrectCount,
                'unanswered_count' => $unansweredCount,
                'average_answered_count' => $participantCount > 0
                    ? round($responseCount / $participantCount, 1)
                    : 0,
                'average_correct_count' => $participantCount > 0
                    ? round($correctCount / $participantCount, 1)
                    : 0,
                'average_incorrect_count' => $participantCount > 0
                    ? round($incorrectCount / $participantCount, 1)
                    : 0,
                'average_unanswered_count' => $participantCount > 0
                    ? round($unansweredCount / $participantCount, 1)
                    : 0,
                'completion_percent' => $possibleResponses > 0
                    ? round(($responseCount / $possibleResponses) * 100, 1)
                    : 0,
                'accuracy_percent' => $responseCount > 0
                    ? round(($correctCount / $responseCount) * 100, 1)
                    : 0,
            ];
        }

        if ($dimension === 'difficulty') {
            $difficultyOrder = ['Easy' => 0, 'Medium' => 1, 'Hard' => 2, 'Unassigned' => 3];
            usort($summaries, static fn (array $left, array $right): int =>
                ($difficultyOrder[$left['label']] ?? 4) <=> ($difficultyOrder[$right['label']] ?? 4)
                ?: strnatcasecmp($left['label'], $right['label'])
            );
        } else {
            usort($summaries, static fn (array $left, array $right): int =>
                strnatcasecmp($left['label'], $right['label'])
            );
        }

        return $summaries;
    }

    private static function summarizeQuestions(Collection $questions, array $answers): array
    {
        $answered = 0;
        $correct = 0;

        foreach ($questions as $question) {
            $answer = self::answerFor($answers, $question);
            if (! is_string($answer) || trim($answer) === '') {
                continue;
            }

            $answered++;
            if ($question->isCorrectAnswer($answer)) {
                $correct++;
            }
        }

        return [
            'answered_count' => $answered,
            'correct_count' => $correct,
            'incorrect_count' => $answered - $correct,
            'accuracy_percent' => $answered > 0 ? round(($correct / $answered) * 100, 1) : 0,
        ];
    }

    private static function answerFor(array $answers, Question $question): mixed
    {
        $stringKey = (string) $question->getKey();

        return $answers[$stringKey] ?? $answers[(int) $stringKey] ?? null;
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

    private static function iso(mixed $value): ?string
    {
        return $value?->toIso8601String();
    }
}
