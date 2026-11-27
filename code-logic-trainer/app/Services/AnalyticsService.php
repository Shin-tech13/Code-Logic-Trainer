<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Models\User;
use App\Models\WhitelistedStudent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyticsService
{
    public const MIN_COHORT_SIZE = 5;

    private const EVENT_TYPES = [
        'question_shown',
        'answer_submitted',
        'answer_changed',
        'attempt_completed',
        'tab_switched',
        'challenge_run',
    ];

    public function record(array $event): void
    {
        if (! in_array($event['event_type'] ?? null, self::EVENT_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported analytics event type.');
        }

        $event['occurred_at'] = $event['occurred_at'] ?? now();
        $event['payload'] = $event['payload'] ?? [];

        dispatch(function () use ($event): void {
            try {
                AnalyticsEvent::query()->create($event);
            } catch (Throwable $exception) {
                Log::warning('Analytics event could not be recorded.', [
                    'event_type' => $event['event_type'],
                    'exception_class' => $exception::class,
                ]);
            }
        })->afterResponse();
    }

    public function professorReport(User $professor, Quiz $quiz, array $filters = []): array
    {
        abort_unless($quiz->professor_id === $professor->id, 403);

        $classroom = null;
        if (! empty($filters['class_id'])) {
            $classroom = Classroom::query()
                ->where('professor_id', $professor->id)
                ->findOrFail($filters['class_id']);
        }

        $managedStudentIds = $this->managedStudentIds($professor, $classroom);
        $sessions = QuizSession::query()
            ->where('quiz_id', $quiz->id)
            ->where('professor_id', $professor->id)
            ->where('type', 'live')
            ->with(['participants' => function ($query) use ($managedStudentIds, $filters) {
                $query->whereIn('student_id', $managedStudentIds);
                if (! empty($filters['date_from'])) {
                    $query->whereDate('joined_at', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $query->whereDate('joined_at', '<=', $filters['date_to']);
                }
            }])
            ->get();

        $participants = $sessions->flatMap->participants;
        $joinedCount = $participants->count();
        $completed = $participants->where('status', 'completed');
        $completedCount = $completed->count();
        $completedStudentCount = $completed->pluck('student_id')->unique()->count();
        $questionCount = $quiz->questions()->count();
        $scorePercentages = $completed->map(
            fn (SessionParticipant $participant) => $questionCount > 0
                ? ($participant->score / $questionCount) * 100
                : 0
        );

        $orderedAttempts = $completed
            ->sortBy(fn (SessionParticipant $participant) => $participant->updated_at?->getTimestamp() ?? 0)
            ->groupBy('student_id');
        $firstScores = $orderedAttempts->map(fn (Collection $attempts) => $this->scorePercent($attempts->first(), $questionCount));
        $latestScores = $orderedAttempts->map(fn (Collection $attempts) => $this->scorePercent($attempts->last(), $questionCount));

        $events = AnalyticsEvent::query()
            ->join('session_participants as analytics_participants', function ($join) {
                $join->on('analytics_participants.quiz_session_id', '=', 'analytics_events.session_id')
                    ->on('analytics_participants.student_id', '=', 'analytics_events.user_id');
            })
            ->where('analytics_events.quiz_id', $quiz->id)
            ->whereIn('analytics_events.session_id', $sessions->modelKeys())
            ->whereIn('analytics_events.user_id', $managedStudentIds)
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('analytics_participants.joined_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('analytics_participants.joined_at', '<=', $filters['date_to']))
            ->get([
                'analytics_events.question_id',
                'analytics_events.session_id',
                'analytics_events.user_id',
                'analytics_events.event_type',
                'analytics_events.payload',
            ]);

        $submittedEvents = $events->where('event_type', 'answer_submitted');
        $responseTimes = $submittedEvents->map(fn (AnalyticsEvent $event) => data_get($event->payload, 'response_time_ms'))
            ->filter(fn ($time) => is_numeric($time))
            ->values();

        $questions = $quiz->questions()->get();
        $questionMetrics = $questions->map(function ($question) use ($participants, $events) {
            $responses = $participants
                ->filter(fn (SessionParticipant $participant) => $participant->status === 'completed')
                ->map(fn (SessionParticipant $participant) => [
                    'student_id' => $participant->student_id,
                    'answer' => data_get($participant->answers, (string) $question->id),
                ])
                ->filter(fn (array $response) => $response['answer'] !== null)
                ->values();
            $respondentCount = $responses->pluck('student_id')->unique()->count();
            $responseCount = $responses->count();
            $answers = $responses->pluck('answer');
            $correctCount = $answers->filter(fn ($answer) => $question->isCorrectAnswer((string) $answer))->count();
            $skippedCount = $answers->filter(fn ($answer) => in_array($answer, ['', '__time_expired__'], true))->count();
            $distractors = $answers
                ->filter(fn ($answer) => $answer !== '' && $answer !== '__time_expired__' && ! $question->isCorrectAnswer((string) $answer))
                ->countBy()
                ->sortDesc();
            $mostChosenDistractor = $distractors->isEmpty() ? null : $distractors->keys()->first();
            $distractorCount = $mostChosenDistractor === null ? 0 : $distractors->first();

            $questionEvents = $events->where('question_id', $question->id);
            $times = $questionEvents
                ->where('event_type', 'answer_submitted')
                ->map(fn (AnalyticsEvent $event) => data_get($event->payload, 'response_time_ms'))
                ->filter(fn ($time) => is_numeric($time))
                ->values();
            $changedQuestionEvents = $questionEvents
                ->where('event_type', 'answer_changed')
                ->unique(fn (AnalyticsEvent $event) => $event->session_id.'|'.$event->user_id)
                ->count();
            $suppressed = $respondentCount < self::MIN_COHORT_SIZE;

            return [
                'id' => $question->id,
                'snippet' => mb_strimwidth(strip_tags($question->question_text), 0, 100, '…'),
                'respondents' => $respondentCount,
                'accuracy' => $suppressed || $responseCount === 0 ? null : round(($correctCount / $responseCount) * 100, 1),
                'skip_timeout' => $suppressed || $responseCount === 0 ? null : round(($skippedCount / $responseCount) * 100, 1),
                'answer_change_rate' => $suppressed || $responseCount === 0 ? null : round(($changedQuestionEvents / $responseCount) * 100, 1),
                'median_seconds' => $suppressed ? null : $this->median($times->map(fn ($time) => ((float) $time) / 1000)),
                'distractor' => $suppressed ? null : $mostChosenDistractor,
                'distractor_rate' => $suppressed || $responseCount === 0 ? null : round(($distractorCount / $responseCount) * 100, 1),
                'confusing' => ! $suppressed
                    && (($responseCount > 0 && ($correctCount / $responseCount) < 0.6)
                        || ($responseCount > 0 && ($distractorCount / $responseCount) > 0.25)),
                'suppressed' => $suppressed,
            ];
        });

        $topicMastery = $this->topicMastery($managedStudentIds, $filters);
        $tabSwitchCount = $participants->sum('tab_switch_count');

        return [
            'quiz' => $quiz,
            'filters' => $filters,
            'classrooms' => $professor->classrooms()->orderBy('title')->get(['id', 'title']),
            'participation' => [
                'joined' => $joinedCount,
                'completed' => $completedCount,
                'completion_rate' => $joinedCount > 0 ? round(($completedCount / $joinedCount) * 100, 1) : null,
                'label' => 'completed participants / joined participants',
            ],
            'score' => [
                'mean' => $scorePercentages->isEmpty() ? null : round($scorePercentages->avg(), 1),
                'median' => $this->median($scorePercentages),
                'first_mean' => $firstScores->isEmpty() ? null : round($firstScores->avg(), 1),
                'latest_mean' => $latestScores->isEmpty() ? null : round($latestScores->avg(), 1),
            ],
            'median_response_seconds' => $this->median($responseTimes->map(fn ($time) => ((float) $time) / 1000)),
            'tab_switch_count' => $tabSwitchCount,
            'score_distribution' => $completedStudentCount < self::MIN_COHORT_SIZE
                ? null
                : $this->scoreDistribution($scorePercentages),
            'score_distribution_suppressed' => $completedStudentCount < self::MIN_COHORT_SIZE,
            'questions' => $questionMetrics,
            'topic_mastery' => $topicMastery,
        ];
    }

    public function studentProgress(User $student): array
    {
        $selfStudy = QuizSession::query()
            ->where('student_id', $student->id)
            ->where('type', 'self_study')
            ->where('status', 'completed')
            ->withCount('questions')
            ->orderBy('updated_at')
            ->get();
        $selfStudyStartedCount = QuizSession::query()
            ->where('student_id', $student->id)
            ->where('type', 'self_study')
            ->count();
        $liveParticipants = SessionParticipant::query()
            ->where('student_id', $student->id)
            ->where('status', 'completed')
            ->with('quizSession.quiz.questions')
            ->orderBy('updated_at')
            ->get();

        $attempts = $selfStudy->map(fn (QuizSession $session) => [
            'id' => $session->id,
            'type' => 'self_study',
            'label' => trim(($session->language ?? '').' · '.($session->topic ?? ''), ' ·'),
            'language' => $session->language ?? 'Other',
            'topic' => $session->topic ?? 'Other',
            'score' => $this->scorePercent($session, $session->questions_count),
            'taken_at' => $session->updated_at,
        ])->concat($liveParticipants->map(fn (SessionParticipant $participant) => [
            'id' => $participant->id,
            'type' => 'live',
            'label' => $participant->quizSession?->quiz?->title ?? 'Live assessment',
            'language' => 'Live assessment',
            'topic' => $participant->quizSession?->quiz?->title ?? 'Live assessment',
            'score' => $this->scorePercent($participant, $participant->quizSession?->quiz?->questions->count() ?? 0),
            'taken_at' => $participant->updated_at,
        ]))->sortBy('taken_at')->values();

        $challengeEvents = AnalyticsEvent::query()
            ->where('user_id', $student->id)
            ->where('event_type', 'challenge_run')
            ->orderBy('occurred_at')
            ->get(['topic', 'language', 'payload', 'occurred_at']);
        $masteryRecords = $attempts->concat($challengeEvents->map(fn (AnalyticsEvent $event) => [
            'language' => $event->language ?? 'PHP',
            'topic' => data_get($event->payload, 'concept', $event->topic ?? 'Other'),
            'score' => data_get($event->payload, 'passed') === true ? 100 : 0,
            'type' => 'challenge',
        ]));

        $mastery = $masteryRecords
            ->groupBy(fn (array $attempt) => $attempt['language'].'|'.$attempt['topic'])
            ->map(function (Collection $group) {
                return [
                    'language' => $group->first()['language'],
                    'topic' => $group->first()['topic'],
                    'accuracy' => round($group->avg('score'), 1),
                    'attempts' => $group->count(),
                    'sources' => $group->pluck('type')->unique()->values(),
                ];
            })->values();

        $challengeGroups = $challengeEvents->groupBy(fn (AnalyticsEvent $event) => data_get($event->payload, 'difficulty', 'beginner'));
        $challengeDifficulty = $challengeGroups->map(fn (Collection $group, string $difficulty) => [
            'difficulty' => ucfirst($difficulty),
            'pass_rate' => round(($group->filter(fn (AnalyticsEvent $event) => data_get($event->payload, 'passed') === true)->count() / max(1, $group->count())) * 100, 1),
            'runs' => $group->count(),
        ])->values();
        $challengeConcepts = $challengeEvents->groupBy(fn (AnalyticsEvent $event) => $event->topic ?? 'Other')
            ->map(fn (Collection $group, string $concept) => [
                'concept' => $concept,
                'pass_rate' => round(($group->filter(fn (AnalyticsEvent $event) => data_get($event->payload, 'passed') === true)->count() / max(1, $group->count())) * 100, 1),
                'runs' => $group->count(),
            ])->values();
        $failures = $challengeEvents
            ->filter(fn (AnalyticsEvent $event) => data_get($event->payload, 'passed') !== true)
            ->map(fn (AnalyticsEvent $event) => data_get($event->payload, 'failed_test_category', 'edge_case_assertion_failure'))
            ->countBy()
            ->sortDesc()
            ->take(4);

        $weakestTopic = $mastery
            ->where('language', '!=', 'Live assessment')
            ->sortBy('accuracy')
            ->first();

        return [
            'mastery' => $mastery,
            'attempts' => $attempts->sortByDesc('taken_at')->take(12)->sortBy('taken_at')->values(),
            'total_attempts' => $attempts->count(),
            'self_study_completion' => [
                'started' => $selfStudyStartedCount,
                'completed' => $selfStudy->count(),
                'rate' => $selfStudyStartedCount > 0
                    ? round(($selfStudy->count() / $selfStudyStartedCount) * 100, 1)
                    : null,
                'label' => 'completed attempts / started attempts',
            ],
            'challenge_runs' => $challengeEvents->count(),
            'challenge_pass_rate' => $challengeEvents->isEmpty()
                ? null
                : round(($challengeEvents->filter(fn (AnalyticsEvent $event) => data_get($event->payload, 'passed') === true)->count() / $challengeEvents->count()) * 100, 1),
            'challenge_difficulty' => $challengeDifficulty,
            'challenge_concepts' => $challengeConcepts,
            'challenge_failures' => $failures,
            'weakest_topic' => $weakestTopic,
        ];
    }

    public function studentWidget(User $student): array
    {
        $progress = $this->studentProgress($student);

        return [
            'attempt_count' => $progress['total_attempts'],
            'weakest_topic' => $progress['weakest_topic'],
        ];
    }

    private function managedStudentIds(User $professor, ?Classroom $classroom = null): array
    {
        if ($classroom) {
            return $classroom->students()->where('users.role', 'student')->pluck('users.id')->all();
        }

        $classroomIds = $professor->classrooms()->pluck('id');
        $classroomStudentIds = DB::table('classroom_student')
            ->whereIn('classroom_id', $classroomIds)
            ->pluck('student_id');
        $whitelistedEmails = WhitelistedStudent::query()
            ->where('professor_id', $professor->id)
            ->where('status', 'active')
            ->pluck('email');
        $whitelistedStudentIds = User::query()
            ->whereIn('personal_email', $whitelistedEmails)
            ->where('role', 'student')
            ->pluck('id');
        $createdStudentIds = User::query()
            ->where('created_by_user_id', $professor->id)
            ->where('role', 'student')
            ->pluck('id');

        $classroomStudentIds = User::query()
            ->whereIn('id', $classroomStudentIds)
            ->where('role', 'student')
            ->pluck('id');

        return $classroomStudentIds
            ->merge($whitelistedStudentIds)
            ->merge($createdStudentIds)
            ->unique()
            ->values()
            ->all();
    }

    private function topicMastery(array $studentIds, array $filters): Collection
    {
        $query = QuizSession::query()
            ->whereIn('student_id', $studentIds)
            ->where('type', 'self_study')
            ->where('status', 'completed')
            ->withCount('questions')
            ->orderBy('language')
            ->orderBy('topic');
        if (! empty($filters['date_from'])) {
            $query->whereDate('updated_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('updated_at', '<=', $filters['date_to']);
        }

        return $query->get()
            ->groupBy(fn (QuizSession $session) => $session->language.'|'.$session->topic)
            ->map(function (Collection $group) {
                $studentCount = $group->pluck('student_id')->unique()->count();
                $suppressed = $studentCount < self::MIN_COHORT_SIZE;
                $studentScores = $group->groupBy('student_id')->map(
                    fn (Collection $attempts) => $attempts->avg(fn (QuizSession $session) => $this->scorePercent($session, $session->questions_count))
                );

                return [
                    'language' => $group->first()->language,
                    'topic' => $group->first()->topic,
                    'student_count' => $studentCount,
                    'accuracy' => $suppressed ? null : round($studentScores->avg(), 1),
                    'suppressed' => $suppressed,
                ];
            })->values();
    }

    private function scoreDistribution(Collection $scores): array
    {
        $buckets = [
            '0–49%' => [0, 50],
            '50–69%' => [50, 70],
            '70–84%' => [70, 85],
            '85–100%' => [85, 101],
        ];

        return collect($buckets)->map(fn (array $range) => $scores
            ->filter(fn ($score) => $score >= $range[0] && $score < $range[1])
            ->count())->all();
    }

    private function scorePercent(?object $attempt, int $questionCount): float
    {
        return ! $attempt || $questionCount < 1 ? 0 : round(((int) $attempt->score / $questionCount) * 100, 1);
    }

    private function median(Collection $values): ?float
    {
        $sorted = $values->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value)->sort()->values();
        $count = $sorted->count();
        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        return round($count % 2
            ? $sorted[$middle]
            : ($sorted[$middle - 1] + $sorted[$middle]) / 2, 1);
    }
}
