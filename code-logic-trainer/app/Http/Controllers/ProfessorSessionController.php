<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProfessorSessionController extends Controller
{
    /**
     * Launch a new live session for a quiz (generate unique code, status waiting).
     */
    public function launch(Quiz $quiz)
    {
        if ($quiz->professor_id !== Auth::id()) {
            abort(403);
        }

        if ($quiz->questions()->count() === 0) {
            return back()->withErrors(['quiz' => 'This quiz has no questions yet.']);
        }

        // One active (waiting/in_progress) session per quiz to avoid confusion.
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
            'quiz_code'    => $this->generateUniqueCode(),
            'score'        => 0,
        ]);

        return redirect()->route('professor.sessions.lobby', $session->id);
    }

    /**
     * Lobby dashboard with live participant list.
     */
    public function lobby(QuizSession $session)
    {
        if ($session->professor_id !== Auth::id()) {
            abort(403);
        }

        $session->load('quiz', 'participants.student');

        return view('professor.lobby', compact('session'));
    }

    /**
     * JSON poll endpoint for the lobby participant list.
     */
    public function lobbyStatus(QuizSession $session)
    {
        if ($session->professor_id !== Auth::id()) {
            abort(403);
        }

        $session->load('quiz', 'participants.student');

        return response()->json([
            'status'       => $session->status,
            'quiz_code'    => $session->quiz_code,
            'started_at'   => $session->started_at?->toIso8601String(),
            'quiz_title'   => $session->quiz->title,
            'question_count' => $session->quiz->question_count,
            'participants' => $session->participants->map(fn ($p) => [
                'id'         => $p->id,
                'name'       => $p->student->name,
                'email'      => $p->student->email,
                'status'     => $p->status,
                'score'      => $p->score,
                'joined_at'  => $p->joined_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Official Start: flip status to in_progress and stamp started_at.
     */
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

    /**
     * End a live session.
     */
    public function end(QuizSession $session)
    {
        if ($session->professor_id !== Auth::id()) {
            abort(403);
        }

        $session->update(['status' => 'completed']);

        return back()->with('status', 'Session ended.');
    }

    /**
     * Generate a unique alphanumeric quiz code, e.g. QZ-8492.
     */
    protected function generateUniqueCode(): string
    {
        do {
            $code = 'QZ-' . strtoupper(Str::random(4));
        } while (QuizSession::where('quiz_code', $code)->exists());

        return $code;
    }
}
