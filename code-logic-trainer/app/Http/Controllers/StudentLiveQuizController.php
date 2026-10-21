<?php

namespace App\Http\Controllers;

use App\Models\QuizSession;
use App\Models\SessionParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StudentLiveQuizController extends Controller
{
    /**
     * Join page: enter the quiz code.
     */
    public function join()
    {
        return view('student.join');
    }

    /**
     * Submit a quiz code and join the session (or land in its lobby).
     */
    public function joinSession(Request $request)
    {
        $request->validate([
            'quiz_code' => ['required', 'string'],
        ]);

        $code = strtoupper(trim($request->input('quiz_code')));

        $session = QuizSession::live()
            ->where('quiz_code', $code)
            ->whereIn('status', ['waiting', 'in_progress'])
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'quiz_code' => 'No active session found for that code. Double-check the code and try again.',
            ]);
        }

        // Late joins allowed even after official start.
        $participant = SessionParticipant::firstOrCreate(
            [
                'quiz_session_id' => $session->id,
                'student_id'      => Auth::id(),
            ],
            [
                'status'    => 'joined',
                'joined_at' => now(),
            ]
        );

        return redirect()->route('student.live.lobby', $session->id);
    }

    /**
     * Student lobby / waiting room (and live quiz if already started).
     */
    public function lobby(QuizSession $session)
    {
        if ($session->type !== 'live') {
            abort(404);
        }

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

    /**
     * JSON poll endpoint for the student lobby.
     * Returns session status, participant list, and (when started) redirect target.
     */
    public function lobbyStatus(QuizSession $session)
    {
        if ($session->type !== 'live') {
            abort(404);
        }

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
            'started_at'    => $session->started_at?->toIso8601String(),
            'participants'  => $session->participants->map(fn ($p) => [
                'name'       => $p->student->name,
                'status'     => $p->status,
                'score'      => $p->score,
            ]),
        ]);
    }

    /**
     * Live quiz-taking screen (shows questions once the session started).
     */
    public function take(QuizSession $session)
    {
        if ($session->type !== 'live') {
            abort(404);
        }

        $participant = SessionParticipant::where('quiz_session_id', $session->id)
            ->where('student_id', Auth::id())
            ->first();

        if (! $participant) {
            return redirect()->route('student.live.join');
        }

        if ($session->status === 'waiting') {
            return redirect()->route('student.live.lobby', $session->id);
        }

        $session->load('quiz.questions');

        return view('student.take', compact('session', 'participant'));
    }

    /**
     * Submit the student's live quiz score.
     */
    public function submit(Request $request, QuizSession $session)
    {
        if ($session->type !== 'live') {
            abort(404);
        }
        abort_unless($session->status === 'in_progress', 409, 'This live quiz is not accepting submissions.');

        $participant = SessionParticipant::where('quiz_session_id', $session->id)
            ->where('student_id', Auth::id())
            ->whereIn('status', ['joined', 'in_progress'])
            ->first();

        if (! $participant) {
            abort(403, 'You are not an active participant in this session.');
        }

        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'string', 'max:1000'],
        ]);

        $questions = $session->quiz->questions()->get();
        $answers = $validated['answers'];
        $questionIds = $questions->modelKeys();

        if (count($answers) !== count($questionIds)
            || array_diff(array_map('strval', array_keys($answers)), array_map('strval', $questionIds))) {
            abort(422, 'Submit one answer for every question in this quiz.');
        }

        $score = 0;
        foreach ($questions as $question) {
            $answer = $answers[$question->id] ?? null;
            $normalizedAnswer = trim($answer);
            $normalizedOptions = array_map(
                static fn ($option) => trim((string) $option),
                $question->options ?? []
            );

            if (! in_array($normalizedAnswer, $normalizedOptions, true)) {
                abort(422, 'A submitted answer is not one of the available options.');
            }

            $answers[$question->id] = $normalizedAnswer;

            if (hash_equals(trim((string) $question->correct_answer), $normalizedAnswer)) {
                $score++;
            }
        }

        $participant->update([
            'score' => $score,
            'status' => 'completed',
            'answers' => $answers,
        ]);

        return response()->json([
            'success'      => true,
            'score'        => $score,
            'total'        => count($questionIds),
            'redirect_url' => route('student.live.complete', $session->id),
        ]);
    }

    /**
     * Completion screen for a live quiz.
     */
    public function complete(QuizSession $session)
    {
        if ($session->type !== 'live') {
            abort(404);
        }

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
}
