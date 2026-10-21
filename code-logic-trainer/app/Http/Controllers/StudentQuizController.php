<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\QuizGeneratorService;
use App\Models\QuizSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class StudentQuizController extends Controller
{
    protected QuizGeneratorService $aiService;

    public function __construct(QuizGeneratorService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Show the filter selection dashboard.
     * Also loads the student's latest quiz session (if any).
     */
    public function index()
    {
        // FIXED: Changed user_id to student_id
        $session = QuizSession::where('student_id', Auth::id())
            ->latest()
            ->first();

        return view('student.dashboard', compact('session'));
    }

    /**
     * Process choices, call the AI, persist the session & questions.
     * Always returns JSON so the Alpine fetch() handler can read it.
     */
    public function generate(Request $request)
    {
        // Allow PHP to run for up to 3 minutes so the AI has time to generate 10 questions
        set_time_limit(180);

        try {
            $validated = $request->validate([
                'language' => ['required', 'string', 'in:PHP,JavaScript,Python,Java'],
                'topic'    => ['required', 'string', 'max:255'],
            ]);

            // Call Gemini AI via your service
            $questionsData = $this->aiService->generateStudentQuiz(
                $validated['language'],
                $validated['topic'],
                10
            );

            // Guard against empty / malformed AI responses
            if (empty($questionsData) || ! is_array($questionsData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The AI returned an empty quiz. Please try a different topic.',
                ], 422);
            }

            // Persist session + questions atomically so a partial failure
            // never leaves an orphaned session behind
            $session = DB::transaction(function () use ($validated, $questionsData) {
                $session = QuizSession::create([
                    'student_id' => Auth::id(), // FIXED: Changed user_id to student_id
                    'language'   => $validated['language'],
                    'topic'      => $validated['topic'],
                ]);

                foreach ($questionsData as $q) {
                    if (! is_array($q)) {
                        continue; // skip malformed entries instead of crashing
                    }

                    $session->questions()->create([
                        'question_text'  => $q['question_text']  ?? '',
                        'options'        => $q['options']        ?? [],
                        'correct_answer' => $q['correct_answer'] ?? '',
                        'explanation'    => $q['explanation']    ?? '',
                    ]);
                }

                return $session;
            });

            return response()->json([
                'success'      => true,
                'redirect_url' => route('student.play', $session->id), // Redirects to the game
                'session_id'   => $session->id,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Quiz generation failed', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Play the quiz game interface.
     */
    public function play(QuizSession $session)
    {
        abort_unless($session->student_id === Auth::id(), 403);

        $session->load('questions');
        return view('student.play', compact('session'));
    }

    /**
     * Show the answer review after a student has submitted their quiz.
     */
    public function results(QuizSession $session)
    {
        abort_unless($session->student_id === Auth::id(), 403);
        abort_unless($session->status === 'completed', 409, 'Submit this quiz before viewing its results.');

        $session->load('questions');

        return view('student.results', compact('session'));
    }

    /**
     * Submit a quiz score.
     */
    public function submit(Request $request, QuizSession $session)
    {
        abort_unless($session->student_id === Auth::id(), 403);
        abort_unless($session->status === 'active', 409, 'This quiz has already been submitted.');

        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'string', 'max:1000'],
        ]);

        $questions = $session->questions()->get();
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

        $session->update([
            'score'  => $score,
            'status' => 'completed',
            'answers' => $answers,
        ]);

        return response()->json([
            'success'      => true,
            'score'        => $score,
            'total'        => count($questionIds),
            'redirect_url' => route('student.results', $session),
        ]);
    }
}
