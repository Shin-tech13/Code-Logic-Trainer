<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidQuizOutputException;
use App\Exceptions\QuizProviderConfigurationException;
use App\Exceptions\QuizProviderException;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Models\UploadedFile as StoredUpload;
use App\Services\GeminiQuizService;
use App\Services\QuizGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StudentQuizController extends Controller
{
    public const TIMEOUT_ANSWER = '__time_expired__';

    protected QuizGeneratorService $aiService;

    protected GeminiQuizService $documentQuizService;

    public function __construct(QuizGeneratorService $aiService, GeminiQuizService $documentQuizService)
    {
        $this->aiService = $aiService;
        $this->documentQuizService = $documentQuizService;
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

    public function history()
    {
        $studentId = Auth::id();
        $history = collect();

        $selfStudySessions = QuizSession::query()
            ->with('questions')
            ->where('student_id', $studentId)
            ->where('type', 'self_study')
            ->where('status', 'completed')
            ->get();

        foreach ($selfStudySessions as $session) {
            $history->push($this->historyEntry(
                title: trim(($session->language ?? '').': '.($session->topic ?? ''), ': '),
                takenAt: $session->updated_at,
                score: $session->score,
                questions: $session->questions,
                answers: $session->answers ?? [],
            ));
        }

        $liveParticipants = SessionParticipant::query()
            ->with('quizSession.quiz.questions')
            ->where('student_id', $studentId)
            ->where('status', 'completed')
            ->get();

        foreach ($liveParticipants as $participant) {
            $session = $participant->quizSession;
            if (! $session || $session->type !== 'live' || ! $session->quiz) {
                continue;
            }

            $history->push($this->historyEntry(
                title: $session->quiz->title,
                takenAt: $participant->updated_at,
                score: $participant->score,
                questions: $session->quiz->questions,
                answers: $participant->answers ?? [],
            ));
        }

        return view('student.history', [
            'history' => $history->sortByDesc('taken_at')->values(),
        ]);
    }

    private function historyEntry(string $title, $takenAt, int $score, $questions, array $answers): array
    {
        $incorrect = $questions->filter(function ($question) use ($answers) {
            $answer = $answers[$question->id] ?? null;

            return ! $question->isCorrectAnswer($answer);
        })->map(function ($question) use ($answers) {
            $answer = $answers[$question->id] ?? null;

            return [
                'question' => $question->question_text,
                'answer' => $answer === self::TIMEOUT_ANSWER
                    ? 'Time expired'
                    : (trim((string) $answer) !== '' ? $answer : 'No answer submitted'),
                'correct_answer' => $question->correct_answer,
                'explanation' => filled($question->explanation)
                    ? $question->explanation
                    : 'No explanation provided.',
            ];
        })->values();

        return [
            'title' => $title !== '' ? $title : 'Quiz',
            'taken_at' => $takenAt,
            'score' => $score,
            'total' => $questions->count(),
            'incorrect' => $incorrect,
        ];
    }

    /**
     * Generate a self-study quiz from a privately stored student PDF.
     */
    public function generateFromDocument(Request $request)
    {
        set_time_limit(300);
        $upload = null;
        $requestId = (string) Str::uuid();
        $failureStage = 'validation';
        $extractedText = null;

        try {
            $validated = $request->validate([
                'pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
                'title' => ['required', 'string', 'max:255'],
                'language' => ['required', 'string', 'in:PHP,JavaScript,Python,Java'],
                'count' => ['nullable', 'integer', 'min:0', 'max:50'],
                'quiz_format' => ['required', 'in:multiple_choice,fill_in_the_blank'],
            ]);

            $failureStage = 'file_storage';
            $file = $request->file('pdf');
            $header = file_get_contents($file->getRealPath(), false, null, 0, 5);
            if ($file->getMimeType() !== 'application/pdf' || $header !== '%PDF-') {
                throw ValidationException::withMessages([
                    'pdf' => 'The uploaded file must contain a valid PDF document.',
                ]);
            }

            $path = $file->store('uploads/pdf', 'local');
            if (! is_string($path) || $path === '') {
                throw new RuntimeException('The PDF could not be stored.');
            }

            $upload = StoredUpload::create([
                'student_id' => Auth::id(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'status' => 'pending',
            ]);

            $failureStage = 'pdf_extraction';
            $extractedText = $this->documentQuizService->extractPdfText(Storage::disk('local')->path($path));
            if (trim($extractedText) === '') {
                throw new RuntimeException('The PDF did not contain extractable text.');
            }

            $failureStage = 'quiz_generation';
            $questionsData = $this->aiService->generateDocumentQuizFromText(
                $extractedText,
                isset($validated['count']) && (int) $validated['count'] > 0
                    ? (int) $validated['count']
                    : null,
                $validated['quiz_format'],
            );

            $failureStage = 'persistence';
            $session = DB::transaction(function () use ($validated, $questionsData, $upload) {
                $session = QuizSession::create([
                    'student_id' => Auth::id(),
                    'language' => $validated['language'],
                    'topic' => $validated['title'],
                    'source_file_id' => $upload->id,
                    'quiz_format' => $validated['quiz_format'],
                ]);

                foreach ($questionsData as $question) {
                    $session->questions()->create([
                        'question_text' => $question['question_text'],
                        'options' => $question['options'],
                        'question_type' => $question['question_type'],
                        'correct_answer' => $question['correct_answer'],
                        'accepted_answers' => $question['accepted_answers'],
                        'explanation' => $question['explanation'],
                        'needs_review' => $question['needs_review'],
                    ]);
                }

                $upload->update(['status' => 'processed']);

                return $session;
            });

            return response()->json([
                'success' => true,
                'question_count' => count($questionsData),
                'redirect_url' => route('student.play', $session),
                'session_id' => $session->id,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the highlighted upload details.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            $provider = match (true) {
                $e instanceof QuizProviderException,
                $e instanceof QuizProviderConfigurationException,
                $e instanceof InvalidQuizOutputException => $e->providerName,
                $failureStage === 'pdf_extraction' => 'gemini',
                $failureStage === 'quiz_generation' => 'groq',
                default => null,
            };
            $failureStage = $e instanceof InvalidQuizOutputException
                ? 'ai_output_parsing'
                : $failureStage;

            Log::channel('stderr')->error('Student PDF quiz generation failed.', [
                'provider' => $provider,
                'failure_stage' => $failureStage,
                'exception_class' => $e::class,
                'underlying_exception_class' => $e->getPrevious() ? $e->getPrevious()::class : null,
                'http_status' => $e instanceof QuizProviderException ? $e->httpStatus : null,
                'provider_error_code' => $e instanceof QuizProviderException ? $e->providerErrorCode : null,
                'extracted_text_empty' => $extractedText === null || trim($extractedText) === '',
                'extracted_text_character_count' => $extractedText === null ? 0 : mb_strlen($extractedText),
                'request_id' => $requestId,
            ]);

            if ($upload) {
                $upload->update(['status' => 'failed']);
            }

            return response()->json([
                'success' => false,
                'message' => match ($failureStage) {
                    'pdf_extraction' => 'Text could not be extracted from this PDF. Try a searchable PDF and upload it again.',
                    'ai_output_parsing' => 'The generated quiz could not be validated. Try a clearer PDF or fewer questions.',
                    'quiz_generation' => 'Quiz generation is temporarily unavailable. Please try again later.',
                    default => 'Quiz generation could not be completed. Check the PDF and try again later.',
                },
            ], 502);
        }
    }

    /**
     * Process choices, call the AI, persist the session & questions.
     * Always returns JSON so the Alpine fetch() handler can read it.
     */
    public function generate(Request $request)
    {
        // Allow PHP to run for up to 3 minutes so the AI has time to generate 10 questions
        set_time_limit(180);
        $requestId = (string) Str::uuid();

        try {
            $validated = $request->validate([
                'language' => ['required', 'string', 'in:PHP,JavaScript,Python,Java'],
                'topic' => ['required', 'string', 'max:255'],
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
                    'language' => $validated['language'],
                    'topic' => $validated['topic'],
                ]);

                foreach ($questionsData as $q) {
                    if (! is_array($q)) {
                        continue; // skip malformed entries instead of crashing
                    }

                    $session->questions()->create([
                        'question_text' => $q['question_text'] ?? '',
                        'options' => $q['options'] ?? [],
                        'question_type' => 'multiple_choice',
                        'correct_answer' => $q['correct_answer'] ?? '',
                        'accepted_answers' => [],
                        'explanation' => $q['explanation'] ?? '',
                    ]);
                }

                return $session;
            });

            return response()->json([
                'success' => true,
                'redirect_url' => route('student.play', $session->id), // Redirects to the game
                'session_id' => $session->id,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::channel('stderr')->error('Student quiz generation failed.', [
                'exception_class' => $e::class,
                'request_id' => $requestId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Quiz generation could not be completed. Please try again later.',
            ], 502);
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
     * Check one answer for the student-created quiz without exposing answers in the page.
     */
    public function answer(Request $request, QuizSession $session)
    {
        abort_unless($session->student_id === Auth::id(), 403);
        abort_unless($session->status === 'active', 409, 'This quiz has already been submitted.');

        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'answer' => ['nullable', 'string', 'max:1000'],
            'timed_out' => ['required', 'boolean'],
        ]);

        $question = $session->questions()->findOrFail($validated['question_id']);
        $timedOut = $validated['timed_out'];
        $answer = $validated['answer'];
        $normalizedAnswer = trim((string) $answer);
        $normalizedOptions = array_map(
            static fn ($option) => trim((string) $option),
            $question->options ?? []
        );

        if ($timedOut) {
            abort_if($answer !== null && $normalizedAnswer !== '', 422, 'Timed-out answers cannot include a selected option.');
        } elseif ($question->question_type === 'fill_in_the_blank') {
            // Free-text answers are checked against the stored accepted answers below.
        } elseif (! in_array($normalizedAnswer, $normalizedOptions, true)) {
            abort(422, 'A submitted answer is not one of the available options.');
        }

        $isCorrect = ! $timedOut && $question->isCorrectAnswer($normalizedAnswer);

        $response = [
            'success' => true,
            'is_correct' => $isCorrect,
        ];

        if (! $isCorrect) {
            $response['correct_answer'] = $question->correct_answer;
            $response['explanation'] = $question->explanation;
        }

        return response()->json($response);
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
            'answers.*' => ['present', 'nullable', 'string', 'max:1000'],
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
            $normalizedAnswer = trim((string) $answer);
            $normalizedOptions = array_map(
                static fn ($option) => trim((string) $option),
                $question->options ?? []
            );

            if ($normalizedAnswer === self::TIMEOUT_ANSWER) {
                continue;
            }

            if ($normalizedAnswer !== self::TIMEOUT_ANSWER
                && $question->question_type !== 'fill_in_the_blank'
                && $normalizedAnswer !== ''
                && ! in_array($normalizedAnswer, $normalizedOptions, true)) {
                abort(422, 'A submitted answer is not one of the available options.');
            }

            $answers[$question->id] = $normalizedAnswer;

            if ($question->isCorrectAnswer($normalizedAnswer)) {
                $score++;
            }
        }

        $session->update([
            'score' => $score,
            'status' => 'completed',
            'answers' => $answers,
        ]);

        return response()->json([
            'success' => true,
            'score' => $score,
            'total' => count($questionIds),
            'redirect_url' => route('student.results', $session),
        ]);
    }
}
