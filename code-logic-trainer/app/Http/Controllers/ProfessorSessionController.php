<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Models\User;
use App\Support\LiveQuizAnalytics;
use App\Support\LiveSessionRoster;
use App\Support\QuestionTimer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfessorSessionController extends Controller
{
    public function create(Quiz $quiz, LiveSessionRoster $roster)
    {
        if ($quiz->professor_id !== Auth::id()) {
            abort(403);
        }

        if ($quiz->questions()->count() === 0) {
            return redirect()->route('professor.quizzes.show', $quiz)
                ->withErrors(['quiz' => 'This quiz has no questions yet.']);
        }

        $professor = $this->authenticatedProfessor();
        $classrooms = $professor->classrooms()
            ->withCount(['students', 'emailAssignments'])
            ->orderBy('title')
            ->get();
        $rosterOptions = $roster->selectableInvitees($professor);
        $activeWhitelistCount = $professor->whitelistedStudents()->where('status', 'active')->count();

        return view('professor.sessions.create', compact(
            'quiz',
            'classrooms',
            'rosterOptions',
            'activeWhitelistCount',
        ));
    }

    public function launch(Request $request, Quiz $quiz, LiveSessionRoster $roster)
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
            ->where('professor_id', Auth::id())
            ->whereIn('status', ['waiting', 'in_progress'])
            ->first();

        if ($existing) {
            return redirect()->route('professor.sessions.lobby', $existing->id);
        }

        $validated = $request->validate([
            'roster_mode' => ['sometimes', 'in:saved_class,manual,all_whitelisted'],
            'classroom_id' => ['required_if:roster_mode,saved_class', 'nullable', 'integer'],
            'roster_emails' => ['nullable', 'array', 'max:500'],
            'roster_emails.*' => ['required', 'email', 'max:255'],
            'manual_emails' => ['nullable', 'string', 'max:10000'],
            'is_make_up' => ['sometimes', 'boolean'],
            'make_up_label' => ['nullable', 'string', 'max:120'],
        ]);
        $rosterMode = $validated['roster_mode'] ?? 'all_whitelisted';
        [$inviteeEmails, $classroomId] = $this->resolveRosterSelection(
            $this->authenticatedProfessor(),
            $rosterMode,
            $validated,
            $roster,
        );
        $isMakeUp = (bool) ($validated['is_make_up'] ?? false);
        $makeUpLabel = $isMakeUp
            ? (trim((string) ($validated['make_up_label'] ?? '')) ?: null)
            : null;

        $session = DB::transaction(function () use (
            $quiz,
            $rosterMode,
            $classroomId,
            $inviteeEmails,
            $isMakeUp,
            $makeUpLabel,
        ) {
            $session = QuizSession::create([
                'quiz_id'      => $quiz->id,
                'professor_id' => Auth::id(),
                'status'       => 'waiting',
                'type'         => 'live',
                'quiz_format'  => $quiz->quiz_format ?? 'multiple_choice',
                'quiz_code'    => $this->generateUniqueCode(),
                'score'        => 0,
                'seconds_per_question' => QuestionTimer::normalize($quiz->seconds_per_question),
                'is_make_up' => $isMakeUp,
                'make_up_label' => $makeUpLabel,
                'roster_mode' => $rosterMode,
                'classroom_id' => $classroomId,
            ]);

            $this->storeInvitees($session, $inviteeEmails);

            return $session;
        });

        return redirect()->route('professor.sessions.lobby', $session->id);
    }

    public function lobby(QuizSession $session)
    {
        $this->authorizeSession($session);

        $session->load('quiz.questions', 'participants.student', 'classroom');
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
            'is_make_up' => $session->is_make_up,
            'make_up_label' => $session->make_up_label,
            'roster_mode' => $session->roster_mode,
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
            'is_make_up' => $session->is_make_up,
            'make_up_label' => $session->make_up_label,
            'roster_mode' => $session->roster_mode,
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

    /**
     * @return array{0: list<string>, 1: int|null}
     */
    private function resolveRosterSelection(
        User $professor,
        string $mode,
        array $validated,
        LiveSessionRoster $roster,
    ): array {
        if ($mode === 'saved_class') {
            $classroom = $professor->classrooms()
                ->with([
                    'students:id,personal_email',
                    'emailAssignments:id,classroom_id,email',
                ])
                ->find($validated['classroom_id'] ?? null);
            abort_unless($classroom, 403, 'You can only select one of your saved classes.');

            $emails = $classroom->students
                ->map(fn ($student) => $roster->normalizeEmail($student->personal_email))
                ->toBase()
                ->merge($classroom->emailAssignments->map(fn ($assignment) => $roster->normalizeEmail($assignment->email)))
                ->filter()
                ->unique()
                ->values()
                ->all();

            return [$emails, $classroom->id];
        }

        if ($mode === 'manual') {
            $selectedEmails = array_values(array_unique(array_filter(array_map(
                fn ($email) => $roster->normalizeEmail((string) $email),
                $validated['roster_emails'] ?? [],
            ))));
            $availableEmails = $roster->selectableInvitees($professor)->pluck('email')->all();

            if (array_diff($selectedEmails, $availableEmails) !== []) {
                throw ValidationException::withMessages([
                    'roster_emails' => 'Choose registered students or active whitelist entries managed by you.',
                ]);
            }

            $manualEmails = preg_split(
                '/[\s,;]+/',
                (string) ($validated['manual_emails'] ?? ''),
                -1,
                PREG_SPLIT_NO_EMPTY,
            ) ?: [];
            $invalidEmails = [];
            foreach ($manualEmails as $email) {
                $email = $roster->normalizeEmail($email);
                if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $invalidEmails[] = $email ?? '';
                } else {
                    $selectedEmails[] = $email;
                }
            }

            if ($invalidEmails !== []) {
                throw ValidationException::withMessages([
                    'manual_emails' => 'Remove or correct these invalid email addresses: '.implode(', ', $invalidEmails),
                ]);
            }

            $emails = array_values(array_unique($selectedEmails));
            if ($emails === []) {
                throw ValidationException::withMessages([
                    'roster_emails' => 'Select at least one student or enter at least one email address.',
                ]);
            }
            if (count($emails) > 500) {
                throw ValidationException::withMessages([
                    'manual_emails' => 'A session roster can include up to 500 students.',
                ]);
            }

            return [$emails, null];
        }

        $emails = $professor->whitelistedStudents()
            ->where('status', 'active')
            ->pluck('email')
            ->map(fn ($email) => $roster->normalizeEmail($email))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [$emails, null];
    }

    /**
     * @param  list<string>  $emails
     */
    private function storeInvitees(QuizSession $session, array $emails): void
    {
        if ($emails === []) {
            return;
        }

        $usersByEmail = \App\Models\User::query()
            ->where('role', 'student')
            ->whereIn(DB::raw('LOWER(personal_email)'), $emails)
            ->get(['id', 'personal_email'])
            ->keyBy(fn ($student) => strtolower(trim((string) $student->personal_email)));

        foreach ($emails as $email) {
            $session->invitees()->create([
                'email' => $email,
                'user_id' => $usersByEmail->get($email)?->id,
            ]);
        }
    }

    private function safeCsvCell(mixed $value): string|int|float
    {
        if (! is_string($value)) {
            return $value ?? '';
        }

        return preg_match('/^[\t\r\n ]*[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }

    private function authenticatedProfessor(): User
    {
        $professor = Auth::user();

        if (! ($professor instanceof User)) {
            abort(403);
        }

        return $professor;
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
