<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Support\LiveQuizAnalytics;
use App\Support\QuestionTimer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfessorSessionController extends Controller
{
    public function launch(Quiz $quiz)
    {
        if ($quiz->professor_id !== Auth::id()) {
            abort(403);
        }

        if ($quiz->questions()->count() === 0) {
            return back()->withErrors(['quiz' => 'This quiz has no questions yet.']);
        }

        // Keep one waiting or active session per quiz.
        $existing = QuizSession::live()
            ->where('quiz_id', $quiz->id)
            ->whereIn('status', ['waiting', 'in_progress'])
            ->first();

        if ($existing) {
            return redirect()->route('professor.sessions.lobby', $existing->id);
        }

        $session = QuizSession::create([
            'quiz_id'      => $quiz->id,
            'professor_id' => Auth::id(),
            'status'       => 'waiting',
            'type'         => 'live',
            'quiz_format'  => $quiz->quiz_format ?? 'multiple_choice',
            'quiz_code'    => $this->generateUniqueCode(),
            'score'        => 0,
            'seconds_per_question' => QuestionTimer::normalize($quiz->seconds_per_question),
        ]);

        return redirect()->route('professor.sessions.lobby', $session->id);
    }

    public function lobby(QuizSession $session)
    {
        $this->authorizeSession($session);

        $session->load('quiz.questions', 'participants.student');
        $analytics = LiveQuizAnalytics::build($session);

        return view('professor.lobby', compact('session', 'analytics'));
    }

    // Timer changes are locked once the session starts.
    public function updateTimer(Request $request, QuizSession $session)
    {
        DB::transaction(function () use ($request, $session) {
            $lockedSession = QuizSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            abort_unless($lockedSession->professor_id === Auth::id(), 403);
            abort_unless($lockedSession->type === 'live', 404);
            abort_unless($lockedSession->status === 'waiting', 409, 'The question timer can only be changed before the session starts.');

            $request->merge([
                'seconds_per_question' => QuestionTimer::normalize($request->input('seconds_per_question')),
            ]);

            $validated = $request->validate([
                'seconds_per_question' => ['required', 'integer', 'between:1,100'],
            ]);

            $lockedSession->update([
                'seconds_per_question' => $validated['seconds_per_question'],
            ]);
        });

        return back()->with('status', 'Question timer settings saved.');
    }

    public function lobbyStatus(QuizSession $session)
    {
        $this->authorizeSession($session);

        $session->load('quiz.questions', 'participants.student');

        return response()->json([
            'status'       => $session->status,
            'quiz_code'    => $session->quiz_code,
            'started_at'   => $session->started_at?->toIso8601String(),
            'quiz_title'   => $session->quiz->title,
            'question_count' => $session->quiz->question_count,
            'seconds_per_question' => QuestionTimer::normalize($session->seconds_per_question),
            'analytics' => LiveQuizAnalytics::build($session),
            'participants' => $session->participants->map(
                fn (SessionParticipant $participant) => LiveQuizAnalytics::participantSummary(
                    $session,
                    $participant,
                    $session->quiz->questions
                )
            )->values(),
            'updated_at' => $session->updated_at?->toIso8601String(),
        ]);
    }

    public function summary(QuizSession $session)
    {
        $this->authorizeSession($session);
        $session->load('quiz.questions', 'participants.student:id,name');

        $analytics = LiveQuizAnalytics::build($session);
        unset($analytics['students']);

        return response()->json([
            'status' => $session->status,
            'quiz_code' => $session->quiz_code,
            'quiz_title' => $session->quiz?->title,
            'question_count' => $analytics['question_count'],
            'seconds_per_question' => QuestionTimer::normalize($session->seconds_per_question),
            'started_at' => $session->started_at?->toIso8601String(),
            'updated_at' => $session->updated_at?->toIso8601String(),
            'refreshed_at' => now()->toIso8601String(),
            'analytics' => $analytics,
        ]);
    }

    public function participants(Request $request, QuizSession $session)
    {
        $this->authorizeSession($session);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,waiting,in_progress,submitted,disconnected'],
            'per_page' => ['nullable', 'integer', 'between:10,50'],
        ]);
        $status = $validated['status'] ?? 'all';
        $cutoff = now()->subSeconds(LiveQuizAnalytics::DISCONNECTED_AFTER_SECONDS);

        $query = $session->participants()
            ->with('student:id,name,email')
            ->join('users as roster_students', 'roster_students.id', '=', 'session_participants.student_id')
            ->select('session_participants.*')
            ->orderBy('roster_students.name')
            ->orderBy('session_participants.id');

        $search = trim($validated['search'] ?? '');
        if ($search !== '') {
            $query->whereHas('student', function ($studentQuery) use ($search) {
                $studentQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($status === 'submitted') {
            $query->where(function ($submitted) {
                $submitted->whereNotNull('session_participants.submitted_at')
                    ->orWhere('session_participants.status', 'completed');
            });
        } elseif ($status === 'waiting') {
            $query->whereNull('session_participants.submitted_at')
                ->where('session_participants.status', '!=', 'completed');
            if ($session->status === 'completed') {
                $query->whereRaw('1 = 0');
            } elseif ($session->status !== 'waiting') {
                $query->whereNull('session_participants.started_at')
                    ->whereNotIn('session_participants.status', ['in_progress', 'completed']);
            }
        } elseif ($status === 'in_progress') {
            $query->where('session_participants.status', '!=', 'completed')
                ->whereNull('session_participants.submitted_at')
                ->where(function ($started) {
                    $started->whereNotNull('session_participants.started_at')
                        ->orWhere('session_participants.status', 'in_progress');
                })
                ->where(function ($active) use ($cutoff) {
                    $active->where('session_participants.last_active_at', '>=', $cutoff)
                        ->orWhere(function ($fallback) use ($cutoff) {
                            $fallback->whereNull('session_participants.last_active_at')
                                ->where('session_participants.updated_at', '>=', $cutoff);
                        });
                });
            if ($session->status !== 'in_progress') {
                $query->whereRaw('1 = 0');
            }
        } elseif ($status === 'disconnected') {
            $query->where('session_participants.status', '!=', 'completed')
                ->whereNull('session_participants.submitted_at')
                ->where(function ($inactive) use ($cutoff, $session) {
                    if ($session->status === 'completed') {
                        $inactive->whereRaw('1 = 1');
                    } else {
                        $inactive->where(function ($started) {
                            $started->whereNotNull('session_participants.started_at')
                                ->orWhere('session_participants.status', 'in_progress');
                        });
                    }

                    if ($session->status === 'in_progress') {
                        $inactive->where(function ($stale) use ($cutoff) {
                            $stale->where('session_participants.last_active_at', '<', $cutoff)
                                ->orWhere(function ($fallback) use ($cutoff) {
                                    $fallback->whereNull('session_participants.last_active_at')
                                        ->where('session_participants.updated_at', '<', $cutoff);
                                });
                        });
                    } elseif ($session->status === 'waiting') {
                        $inactive->whereRaw('1 = 0');
                    }
                });
        }

        $page = $query->paginate($validated['per_page'] ?? 20)->withQueryString();
        $questions = $session->loadMissing('quiz.questions')->quiz?->questions ?? collect();
        $data = $page->getCollection()->map(
            fn (SessionParticipant $participant) => LiveQuizAnalytics::participantSummary($session, $participant, $questions)
        )->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    public function participant(QuizSession $session, int $participant)
    {
        $this->authorizeSession($session);
        $session->load('quiz.questions');

        $participantRecord = $session->participants()
            ->with('student:id,name')
            ->findOrFail($participant);
        $questions = $session->quiz?->questions ?? collect();

        return response()->json([
            'participant' => LiveQuizAnalytics::participantSummary($session, $participantRecord, $questions),
            'questions' => LiveQuizAnalytics::participantQuestionRows($participantRecord, $questions),
        ]);
    }

    public function export(QuizSession $session)
    {
        $this->authorizeSession($session);
        abort_unless($session->status === 'completed', 409, 'Session exports are available after the session ends.');

        $session->load('quiz.questions', 'participants.student');
        $analytics = LiveQuizAnalytics::build($session);
        $rows = [
            [
                'summary',
                'Session summary',
                $session->status,
                $analytics['summary']['participant_count'],
                $analytics['summary']['completed_count'],
                $analytics['summary']['completion_rate_percent'],
                $analytics['summary']['correct_count'],
                $analytics['summary']['answered_count'],
                $analytics['summary']['accuracy_percent'],
                '',
                $analytics['summary']['questions_needing_review'],
                '',
            ],
        ];

        foreach ($analytics['students'] as $participant) {
            $rows[] = [
                'participant',
                $participant['name'],
                $participant['status'],
                '',
                '',
                '',
                $participant['correct_count'],
                $participant['answered_count'],
                $participant['accuracy_percent'],
                '',
                '',
                $participant['submitted_at'] ?? '',
            ];
        }

        foreach ($analytics['questions'] as $question) {
            $rows[] = [
                'question',
                'Question '.$question['number'].': '.$question['question'],
                '',
                '',
                '',
                '',
                $question['correct_count'],
                $question['response_count'],
                $question['accuracy_percent'],
                $question['unanswered_count'],
                '',
                '',
            ];
        }

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, [
                'record_type',
                'name_or_question',
                'status',
                'participants',
                'submitted',
                'completion_rate_percent',
                'correct_count',
                'answered_count',
                'accuracy_percent',
                'unanswered_count',
                'questions_needing_review',
                'submitted_at',
            ]);

            foreach ($rows as $row) {
                fputcsv($stream, array_map($this->safeCsvCell(...), $row));
            }

            fclose($stream);
        }, 'live-session-'.$session->id.'-summary.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // Starting the exam also records its start time.
    public function start(QuizSession $session)
    {
        $started = DB::transaction(function () use ($session) {
            $lockedSession = QuizSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->authorizeSession($lockedSession);

            if ($lockedSession->status !== 'waiting') {
                return false;
            }

            $lockedSession->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            return true;
        });

        if (! $started) {
            return response()->json([
                'success' => false,
                'message' => 'Session is already in progress or completed.',
            ], 422);
        }

        return response()->json([
            'success'     => true,
            'redirect_url' => route('professor.sessions.lobby', $session->id),
        ]);
    }

    public function end(QuizSession $session)
    {
        DB::transaction(function () use ($session) {
            $lockedSession = QuizSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->authorizeSession($lockedSession);

            if ($lockedSession->status !== 'completed') {
                $lockedSession->update(['status' => 'completed']);
            }
        });

        return back()->with('status', 'Session ended.');
    }

    private function authorizeSession(QuizSession $session): void
    {
        abort_unless($session->type === 'live', 404);
        abort_unless($session->professor_id === Auth::id(), 403);
    }

    private function safeCsvCell(mixed $value): string|int|float
    {
        if (! is_string($value)) {
            return $value ?? '';
        }

        return preg_match('/^[\t\r\n ]*[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }

    // Join codes use the QZ-XXXX format.
    protected function generateUniqueCode(): string
    {
        do {
            $code = 'QZ-' . strtoupper(Str::random(4));
        } while (QuizSession::where('quiz_code', $code)->exists());

        return $code;
    }
}
