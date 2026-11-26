<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizSession;
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
        if ($session->professor_id !== Auth::id()) {
            abort(403);
        }

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
        if ($session->professor_id !== Auth::id()) {
            abort(403);
        }

        $session->load('quiz.questions', 'participants.student');

        return response()->json([
            'status'       => $session->status,
            'quiz_code'    => $session->quiz_code,
            'started_at'   => $session->started_at?->toIso8601String(),
            'quiz_title'   => $session->quiz->title,
            'question_count' => $session->quiz->question_count,
            'seconds_per_question' => QuestionTimer::normalize($session->seconds_per_question),
            'analytics' => LiveQuizAnalytics::build($session),
            'participants' => $session->participants->map(fn ($p) => [
                'id'         => $p->id,
                'name'       => $p->student->name,
                'email'      => $p->student->email,
                'status'     => $p->status,
                'score'      => $p->score,
                'joined_at'  => $p->joined_at?->toIso8601String(),
                'tab_switch_count' => $p->tab_switch_count,
                'last_tab_switch_at' => $p->last_tab_switch_at?->toIso8601String(),
                'last_tab_switch_reason' => $p->last_tab_switch_reason,
            ]),
        ]);
    }

    // Starting the exam also records its start time.
    public function start(QuizSession $session)
    {
        if ($session->professor_id !== Auth::id()) {
            abort(403);
        }

        if ($session->status !== 'waiting') {
            return response()->json([
                'success' => false,
                'message' => 'Session is already in progress or completed.',
            ], 422);
        }

        $session->update([
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        return response()->json([
            'success'     => true,
            'redirect_url' => route('professor.sessions.lobby', $session->id),
        ]);
    }

    public function end(QuizSession $session)
    {
        if ($session->professor_id !== Auth::id()) {
            abort(403);
        }

        $session->update(['status' => 'completed']);

        return back()->with('status', 'Session ended.');
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
