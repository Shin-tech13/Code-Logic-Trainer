<?php

namespace App\Http\Controllers;

use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Services\AnalyticsService;
use App\Support\LiveSessionRoster;
use App\Support\QuestionTimer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentLiveQuizController extends Controller
{
    public function __construct(private LiveSessionRoster $sessionRoster)
    {
    }

    public function join()
    {
        return view('student.join');
    }

    public function joinSession(Request $request)
    {
        $request->validate([
            'quiz_code' => ['required', 'string'],
        ]);

        $code = strtoupper(trim($request->input('quiz_code')));

        $session = DB::transaction(function () use ($code) {
            $session = QuizSession::live()
                ->where('quiz_code', $code)
                ->whereIn('status', ['waiting', 'in_progress'])
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw ValidationException::withMessages([
                    'quiz_code' => 'No active session found for that code. Double-check the code and try again.',
                ]);
            }

            if (! $this->sessionRoster->isEligible($session, Auth::user())) {
                throw ValidationException::withMessages([
                    'quiz_code' => 'No active session was found for that code, or your email is not on its roster.',
                ]);
            }

            $participant = SessionParticipant::firstOrCreate(
                [
                    'quiz_session_id' => $session->id,
                    'student_id' => Auth::id(),
                ],
                [
                    'status' => 'joined',
                    'joined_at' => now(),
                ]
            );
            if ($participant->submitted_at === null && $participant->status !== 'completed') {
                $participant->forceFill(['last_active_at' => now()])->save();
            }

            return $session;
        });

        return redirect()->route('student.live.lobby', $session->id);
    }

    public function lobby(QuizSession $session)
    {
        $this->authorizeRoster($session);

        $participant = SessionParticipant::where('quiz_session_id', $session->id)
            ->where('student_id', Auth::id())
            ->first();

        if (! $participant) {
            return redirect()->route('student.live.join')
                ->withErrors(['quiz_code' => 'You have not joined this session yet.']);
        }

        $session->load('quiz', 'participants.student');

        return view('student.lobby', compact('session', 'participant'));
    }

    public function lobbyStatus(QuizSession $session)
    {
        $this->authorizeRoster($session);

        $participant = SessionParticipant::where('quiz_session_id', $session->id)
            ->where('student_id', Auth::id())
            ->first();

        if (! $participant) {
            return response()->json(['joined' => false], 403);
        }

        $session->load('quiz', 'participants.student');

        return response()->json([
            'joined'        => true,
            'status'        => $session->status,
            'quiz_code'     => $session->quiz_code,
            'quiz_title'    => $session->quiz->title,
            'question_count'=> $session->quiz->question_count,
            'seconds_per_question' => QuestionTimer::normalize($session->seconds_per_question),
            'started_at'    => $session->started_at?->toIso8601String(),
            'participants'  => $session->participants->map(fn ($p) => [
                'name'       => $p->student->name,
                'status'     => $p->status,
            ]),
        ]);
    }

    public function take(QuizSession $session, AnalyticsService $analytics)
    {
        $this->authorizeRoster($session);

        $participant = SessionParticipant::where('quiz_session_id', $session->id)
            ->where('student_id', Auth::id())
            ->first();

        if (! $participant) {
            return redirect()->route('student.live.join');
        }

        if ($session->status === 'waiting') {
            return redirect()->route('student.live.lobby', $session->id);
        }

        if ($session->status === 'in_progress'
            && in_array($participant->status, ['joined', 'in_progress'], true)) {
            $participant->forceFill([
                'status' => 'in_progress',
                'started_at' => $participant->started_at ?? now(),
                'last_active_at' => now(),
            ])->save();
        }

        $session->load('quiz.questions');
        foreach ($session->quiz->questions as $question) {
            $analytics->record([
                'user_id' => Auth::id(),
                'role' => Auth::user()->role,
                'session_id' => $session->id,
                'quiz_id' => $session->quiz_id,
                'question_id' => $question->id,
                'event_type' => 'question_shown',
            ]);
        }
        $secondsPerQuestion = QuestionTimer::normalize($session->seconds_per_question);
        $examActive = $session->status === 'in_progress'
            && in_array($participant->status, ['joined', 'in_progress'], true);

        return view('student.take', compact('session', 'participant', 'examActive', 'secondsPerQuestion'));
    }

    public function heartbeat(QuizSession $session)
    {
        $participant = DB::transaction(function () use ($session) {
            $lockedSession = QuizSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeRoster($lockedSession);
            abort_unless($lockedSession->status === 'in_progress', 409, 'This live quiz is not in progress.');

            $participant = SessionParticipant::query()
                ->where('quiz_session_id', $lockedSession->id)
                ->where('student_id', Auth::id())
                ->lockForUpdate()
                ->first();

            abort_unless(
                $participant && in_array($participant->status, ['joined', 'in_progress'], true),
                403,
                'You are not an active participant in this session.'
            );

            $now = now();
            $participant->forceFill([
                'status' => 'in_progress',
                'started_at' => $participant->started_at ?? $now,
                'last_active_at' => $now,
            ])->save();

            return $participant;
        });

        return response()->json([
            'alive' => true,
            'last_active_at' => $participant->last_active_at?->toIso8601String(),
        ]);
    }

    // Record visibility and focus loss for the professor.
    public function flagTabSwitch(QuizSession $session, AnalyticsService $analytics)
    {
        $participant = DB::transaction(function () use ($session) {
            $lockedSession = QuizSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedSession->type === 'live', 404);
            abort_unless($lockedSession->status === 'in_progress', 409, 'This live quiz is not in progress.');
            $this->authorizeRoster($lockedSession);

            $participant = SessionParticipant::query()
                ->where('quiz_session_id', $lockedSession->id)
                ->where('student_id', Auth::id())
                ->lockForUpdate()
                ->first();

            abort_unless($participant, 403, 'You are not a participant in this session.');
            abort_unless(
                in_array($participant->status, ['joined', 'in_progress'], true),
                409,
                'This participant is no longer taking the quiz.'
            );

            $participant->tab_switch_count++;
            $participant->last_tab_switch_at = now();
            $participant->last_active_at = now();
            $participant->last_tab_switch_reason = 'Tab switch / window blur detected';
            $participant->save();

            return $participant;
        });
        $analytics->record([
            'user_id' => Auth::id(),
            'role' => Auth::user()->role,
            'session_id' => $session->id,
            'quiz_id' => $session->quiz_id,
            'event_type' => 'tab_switched',
            'payload' => ['reason' => 'visibility_or_window_blur'],
        ]);

        return response()->json([
            'flagged' => true,
            'tab_switch_count' => $participant->tab_switch_count,
            'last_flagged_at' => $participant->last_tab_switch_at->toIso8601String(),
            'reason' => $participant->last_tab_switch_reason,
        ]);
    }

    // Keep in-progress answers visible to the professor's live analytics.
    public function saveProgress(Request $request, QuizSession $session)
    {
        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'answer' => ['present', 'nullable', 'string', 'max:1000'],
            'response_time_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
        ]);

        $event = DB::transaction(function () use ($validated, $session) {
            $lockedSession = QuizSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedSession->type === 'live', 404);
            abort_unless($lockedSession->status === 'in_progress', 409, 'This live quiz is not accepting answers.');
            $this->authorizeRoster($lockedSession);

            $participant = SessionParticipant::query()
                ->where('quiz_session_id', $lockedSession->id)
                ->where('student_id', Auth::id())
                ->lockForUpdate()
                ->first();

            abort_unless(
                $participant && in_array($participant->status, ['joined', 'in_progress'], true),
                403,
                'You are not an active participant in this session.'
            );

            $quiz = $lockedSession->quiz;
            abort_unless($quiz, 404);
            $question = $quiz->questions()->whereKey($validated['question_id'])->first();
            abort_unless($question, 404, 'This question does not belong to the live quiz.');

            $answer = trim((string) $validated['answer']);
            $normalizedOptions = array_map(
                static fn ($option) => trim((string) $option),
                $question->options ?? []
            );

            if ($answer !== ''
                && $question->question_type !== 'fill_in_the_blank'
                && ! in_array($answer, $normalizedOptions, true)) {
                abort(422, 'The submitted answer is not one of the available options.');
            }

            $answers = $participant->answers ?? [];
            $questionKey = (string) $question->id;
            $changed = array_key_exists($questionKey, $answers) && $answers[$questionKey] !== $answer;
            $answers[(string) $question->id] = $answer;
            $now = now();
            $participant->update([
                'answers' => $answers,
                'status' => 'in_progress',
                'started_at' => $participant->started_at ?? $now,
                'last_active_at' => $now,
            ]);

            return [
                'question_id' => $question->id,
                'topic' => $lockedSession->topic,
                'language' => $lockedSession->language,
                'event_type' => $changed ? 'answer_changed' : 'answer_submitted',
                'payload' => [
                    'selected_option' => $question->question_type === 'fill_in_the_blank' || $answer === '' ? null : $answer,
                    'response_time_ms' => $validated['response_time_ms'] ?? null,
                ],
            ];
        });
        app(AnalyticsService::class)->record([
            'user_id' => Auth::id(),
            'role' => Auth::user()->role,
            'session_id' => $session->id,
            'quiz_id' => $session->quiz_id,
            ...$event,
        ]);

        return response()->json(['saved' => true]);
    }

    public function submit(Request $request, QuizSession $session, AnalyticsService $analytics)
    {
        $result = DB::transaction(function () use ($request, $session) {
            $lockedSession = QuizSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedSession->type === 'live', 404);
            $this->authorizeRoster($lockedSession);

            $participant = SessionParticipant::query()
                ->where('quiz_session_id', $lockedSession->id)
                ->where('student_id', Auth::id())
                ->lockForUpdate()
                ->first();
            abort_unless($participant, 403, 'You are not a participant in this session.');

            $questions = $lockedSession->quiz?->questions()->get() ?? collect();
            if ($participant->submitted_at !== null || $participant->status === 'completed') {
                $storedAnswers = $participant->answers ?? [];
                $score = $questions->filter(function ($question) use ($storedAnswers) {
                    $answer = $storedAnswers[$question->id] ?? $storedAnswers[(string) $question->id] ?? '';

                    return $question->isCorrectAnswer((string) $answer);
                })->count();

                return [
                    'score' => $score,
                    'total' => $questions->count(),
                    'already_submitted' => true,
                    'question_events' => [],
                ];
            }

            abort_unless($lockedSession->status === 'in_progress', 409, 'This live quiz is not accepting submissions.');
            abort_unless(
                in_array($participant->status, ['joined', 'in_progress'], true),
                403,
                'You are not an active participant in this session.'
            );

            $validated = $request->validate([
                'answers' => ['required', 'array', 'min:1'],
                'answers.*' => ['present', 'nullable', 'string', 'max:1000'],
            ]);

            $answers = $validated['answers'];
            $questionIds = $questions->modelKeys();
            if (count($answers) !== count($questionIds)
                || array_diff(array_map('strval', array_keys($answers)), array_map('strval', $questionIds))) {
                abort(422, 'Submit one answer for every question in this quiz.');
            }

            $score = 0;
            $questionEvents = [];
            foreach ($questions as $question) {
                $answer = trim((string) ($answers[$question->id] ?? ''));
                $normalizedOptions = array_map(
                    static fn ($option) => trim((string) $option),
                    $question->options ?? []
                );

                if ($answer !== ''
                    && $question->question_type !== 'fill_in_the_blank'
                    && ! in_array($answer, $normalizedOptions, true)) {
                    abort(422, 'A submitted answer is not one of the available options.');
                }

                $answers[$question->id] = $answer;
                $isCorrect = $question->isCorrectAnswer($answer);
                $score += (int) $isCorrect;
                $questionEvents[] = [
                    'question_id' => $question->id,
                    'answer' => $answer,
                    'is_correct' => $isCorrect,
                    'is_fill_in_the_blank' => $question->question_type === 'fill_in_the_blank',
                ];
            }

            $now = now();
            $participant->update([
                'score' => $score,
                'status' => 'completed',
                'answers' => $answers,
                'started_at' => $participant->started_at ?? $now,
                'last_active_at' => $now,
                'submitted_at' => $now,
            ]);

            return [
                'score' => $score,
                'total' => count($questionIds),
                'already_submitted' => false,
                'question_events' => $questionEvents,
            ];
        });

        if (! $result['already_submitted']) {
            foreach ($result['question_events'] as $questionEvent) {
                $answer = $questionEvent['answer'];
                $analytics->record([
                    'user_id' => Auth::id(),
                    'role' => Auth::user()->role,
                    'session_id' => $session->id,
                    'quiz_id' => $session->quiz_id,
                    'question_id' => $questionEvent['question_id'],
                    'event_type' => 'answer_submitted',
                    'payload' => [
                        'selected_option' => $questionEvent['is_fill_in_the_blank'] || $answer === '' ? null : $answer,
                        'is_correct' => $questionEvent['is_correct'],
                    ],
                ]);
            }
            $analytics->record([
                'user_id' => Auth::id(),
                'role' => Auth::user()->role,
                'session_id' => $session->id,
                'quiz_id' => $session->quiz_id,
                'event_type' => 'attempt_completed',
                'payload' => [
                    'score' => $result['score'],
                    'question_count' => $result['total'],
                    'attempt_type' => 'live',
                ],
            ]);
        }

        return response()->json([
            'success'      => true,
            'score'        => $result['score'],
            'total'        => $result['total'],
            'already_submitted' => $result['already_submitted'],
            'redirect_url' => route('student.live.complete', $session->id),
        ]);
    }

    public function complete(QuizSession $session)
    {
        $this->authorizeRoster($session);

        $participant = SessionParticipant::where('quiz_session_id', $session->id)
            ->where('student_id', Auth::id())
            ->first();

        if (! $participant) {
            return redirect()->route('student.live.join');
        }

        abort_unless($participant->status === 'completed', 409, 'Submit this quiz before viewing its results.');

        $session->load('quiz.questions');

        return view('student.complete', compact('session', 'participant'));
    }

    private function authorizeRoster(QuizSession $session): void
    {
        abort_unless($session->type === 'live', 404);

        $student = Auth::user();
        abort_unless(
            $student && $this->sessionRoster->isEligible($session, $student),
            403,
            'You are not eligible to access this live session.'
        );
    }
}
