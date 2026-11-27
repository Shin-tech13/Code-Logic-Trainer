<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidQuizOutputException;
use App\Exceptions\QuizProviderConfigurationException;
use App\Exceptions\QuizProviderException;
use App\Models\QuizSession;
use App\Models\SessionParticipant;
use App\Models\UploadedFile as StoredUpload;
use App\Services\AnalyticsService;
use App\Services\GeminiQuizService;
use App\Services\QuizGeneratorService;
use App\Support\QuestionTimer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    // Resume the student's latest quiz when one exists.
    public function index(AnalyticsService $analytics)
    {
        $session = QuizSession::where('student_id', Auth::id())
            ->latest()
            ->first();
        $progressWidget = $analytics->studentWidget(Auth::user());

        return view('student.dashboard', compact('session', 'progressWidget'));
    }

    public function history(Request $request)
    {
        $studentId = Auth::id();
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'type' => ['nullable', Rule::in(['self_study', 'live'])],
            'attempt_id' => ['nullable', 'integer'],
        ]);

        $selfStudyAttempts = DB::table('quiz_sessions')
            ->selectRaw("'self_study' as history_type")
            ->addSelect('id as attempt_id', 'updated_at as taken_at', 'score', 'language', 'topic')
            ->selectRaw('NULL as quiz_title')
            ->where('student_id', $studentId)
            ->where('type', 'self_study')
            ->where('status', 'completed');

        $liveAttempts = DB::table('session_participants as participants')
            ->join('quiz_sessions as sessions', 'sessions.id', '=', 'participants.quiz_session_id')
            ->join('quizzes', 'quizzes.id', '=', 'sessions.quiz_id')
            ->selectRaw("'live' as history_type")
            ->addSelect(
                'participants.id as attempt_id',
                'participants.updated_at as taken_at',
                'participants.score',
            )
            ->selectRaw('NULL as language, NULL as topic, quizzes.title as quiz_title')
            ->where('participants.student_id', $studentId)
            ->where('participants.status', 'completed')
            ->where('sessions.type', 'live');

        $attemptsQuery = DB::query()
            ->fromSub($selfStudyAttempts->unionAll($liveAttempts), 'history_attempts')
            ->select('history_attempts.*');

        if (! empty($filters['type'])) {
            $attemptsQuery->where('history_type', $filters['type']);
        }
        if (! empty($filters['attempt_id'])) {
            $attemptsQuery->where('attempt_id', $filters['attempt_id']);
        }
        if (! empty($filters['date_from'])) {
            $attemptsQuery->whereDate('taken_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $attemptsQuery->whereDate('taken_at', '<=', $filters['date_to']);
        }

        $history = $attemptsQuery
            ->orderByDesc('taken_at')
            ->orderByDesc('attempt_id')
            ->paginate(10)
            ->withQueryString();

        $selfStudyIds = $history->getCollection()
            ->where('history_type', 'self_study')
            ->pluck('attempt_id');
        $liveParticipantIds = $history->getCollection()
            ->where('history_type', 'live')
            ->pluck('attempt_id');

        $selfStudySessions = QuizSession::query()
            ->with('questions')
            ->whereIn('id', $selfStudyIds)
            ->get()
            ->keyBy('id');
        $liveParticipants = SessionParticipant::query()
            ->with('quizSession.quiz.questions')
            ->whereIn('id', $liveParticipantIds)
            ->get()
            ->keyBy('id');

        $history->getCollection()->transform(function ($attempt) use ($selfStudySessions, $liveParticipants) {
            if ($attempt->history_type === 'self_study') {
                $session = $selfStudySessions->get($attempt->attempt_id);

                return $this->historyEntry(
                    title: trim(($session->language ?? '').': '.($session->topic ?? ''), ': '),
                    takenAt: $session->updated_at,
                    score: $session->score,
                    questions: $session->questions,
                    answers: $session->answers ?? [],
                    type: 'self_study',
                    attemptId: $attempt->attempt_id,
                );
            }

            $participant = $liveParticipants->get($attempt->attempt_id);
            $session = $participant->quizSession;

            return $this->historyEntry(
                title: $session->quiz->title,
                takenAt: $participant->updated_at,
                score: $participant->score,
                questions: $session->quiz->questions,
                answers: $participant->answers ?? [],
                type: 'live',
                attemptId: $attempt->attempt_id,
            );
        });

        return view('student.history', compact('history', 'filters'));
    }

    private function historyEntry(string $title, $takenAt, int $score, $questions, array $answers, string $type, int $attemptId): array
    {
        $reviewAnswers = $questions->values()->map(function ($question, $index) use ($answers) {
            $answer = $answers[$question->id] ?? null;
            $isCorrect = $question->isCorrectAnswer($answer);

            return [
                'number' => $index + 1,
                'question' => $question->question_text,
                'answer' => $answer === self::TIMEOUT_ANSWER
                    ? 'Time expired'
                    : (trim((string) $answer) !== '' ? $answer : 'No answer submitted'),
                'correct_answer' => $question->correct_answer,
                'explanation' => filled($question->explanation)
                    ? $question->explanation
                    : 'No explanation provided.',
                'is_correct' => $isCorrect,
            ];
        })->values();

        return [
            'title' => $title !== '' ? $title : 'Quiz',
            'taken_at' => $takenAt,
            'score' => $score,
            'total' => $questions->count(),
            'correct_count' => $reviewAnswers->where('is_correct', true)->count(),
            'incorrect' => $reviewAnswers->where('is_correct', false)->values(),
            'answers' => $reviewAnswers,
            'type' => $type,
            'attempt_id' => $attemptId,
        ];
    }

    // Keep generated source PDFs associated with the student who uploaded them.
    public function generateFromDocument(Request $request)
    {
        set_time_limit(300);
        $upload = null;
        $requestId = (string) Str::uuid();
        $failureStage = 'validation';
        $extractedText = null;

        try {
            $request->merge([
                'seconds_per_question' => QuestionTimer::normalize($request->input('seconds_per_question')),
            ]);

            $validated = $request->validate([
                'pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
                'title' => ['required', 'string', 'max:255'],
                'language' => ['required', 'string', 'in:PHP,JavaScript,Python,Java'],
                'count' => ['nullable', 'integer', 'min:0', 'max:150'],
                'quiz_format' => ['required', 'in:multiple_choice,fill_in_the_blank'],
                'seconds_per_question' => ['required', 'integer', 'between:1,100'],
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
                $requestId,
            );

            $failureStage = 'persistence';
            $session = DB::transaction(function () use ($validated, $questionsData, $upload) {
                $session = QuizSession::create([
                    'student_id' => Auth::id(),
                    'language' => $validated['language'],
                    'topic' => $validated['title'],
                    'seconds_per_question' => $validated['seconds_per_question'],
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
                'seconds_per_question' => $session->seconds_per_question,
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
                'rejection_reason' => $e instanceof InvalidQuizOutputException ? $e->reasonCode : null,
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

    // The student dashboard calls this endpoint with fetch and expects JSON.
    public function generate(Request $request)
    {
        // AI generation can take up to three minutes.
        set_time_limit(180);
        $requestId = (string) Str::uuid();

        try {
            $request->merge([
                'seconds_per_question' => QuestionTimer::normalize($request->input('seconds_per_question')),
            ]);

            $validated = $request->validate([
                'language' => ['required', 'string', 'in:PHP,JavaScript,Python,Java'],
                'topic' => ['required', 'string', 'max:255'],
                'seconds_per_question' => ['required', 'integer', 'between:1,100'],
            ]);

            $questionsData = $this->aiService->generateStudentQuiz(
                $validated['language'],
                $validated['topic'],
                10
            );

            if (empty($questionsData) || ! is_array($questionsData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The AI returned an empty quiz. Please try a different topic.',
                ], 422);
            }

            // Keep the session and its questions together if persistence fails.
            $session = DB::transaction(function () use ($validated, $questionsData) {
                $session = QuizSession::create([
                    'student_id' => Auth::id(),
                    'language' => $validated['language'],
                    'topic' => $validated['topic'],
                    'seconds_per_question' => $validated['seconds_per_question'],
                ]);

                foreach ($questionsData as $q) {
                    if (! is_array($q)) {
                        // Ignore malformed provider entries.
                        continue;
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
                'redirect_url' => route('student.play', $session->id),
                'session_id' => $session->id,
                'seconds_per_question' => $session->seconds_per_question,
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

    public function play(QuizSession $session)
    {
        abort_unless($session->student_id === Auth::id(), 403);

        $session->load('questions');
        $secondsPerQuestion = QuestionTimer::normalize($session->seconds_per_question);
        foreach ($session->questions as $question) {
            app(AnalyticsService::class)->record([
                'user_id' => Auth::id(),
                'role' => Auth::user()->role,
                'session_id' => $session->id,
                'question_id' => $question->id,
                'topic' => $session->topic,
                'language' => $session->language,
                'event_type' => 'question_shown',
            ]);
        }

        return view('student.play', compact('session', 'secondsPerQuestion'));
    }

    // Keep answer review locked until submission is complete.
    public function results(QuizSession $session)
    {
        abort_unless($session->student_id === Auth::id(), 403);
        abort_unless($session->status === 'completed', 409, 'Submit this quiz before viewing its results.');

        $session->load('questions');

        return view('student.results', compact('session'));
    }

    // Check answers server-side; keep the answer key out of the quiz page.
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
            // The model checks free-text variants against accepted answers.
        } elseif (! in_array($normalizedAnswer, $normalizedOptions, true)) {
            abort(422, 'A submitted answer is not one of the available options.');
        }

        $isCorrect = ! $timedOut && $question->isCorrectAnswer($normalizedAnswer);

        $response = [
            'success' => true,
            'is_correct' => $isCorrect,
        ];

        $selectionKey = 'analytics.quiz.'.$session->id.'.question.'.$question->id;
        $previousSelection = session($selectionKey);
        if ($previousSelection !== null && $previousSelection !== $normalizedAnswer) {
            app(AnalyticsService::class)->record([
                'user_id' => Auth::id(),
                'role' => Auth::user()->role,
                'session_id' => $session->id,
                'question_id' => $question->id,
                'topic' => $session->topic,
                'language' => $session->language,
                'event_type' => 'answer_changed',
                'payload' => [
                    'selected_option' => $question->question_type === 'fill_in_the_blank' ? null : $normalizedAnswer,
                ],
            ]);
        }
        session([$selectionKey => $normalizedAnswer]);

        if (! $isCorrect) {
            $response['correct_answer'] = $question->correct_answer;
            $response['explanation'] = $question->explanation;
        }

        return response()->json($response);
    }

    public function submit(Request $request, QuizSession $session, AnalyticsService $analytics)
    {
        abort_unless($session->student_id === Auth::id(), 403);
        abort_unless($session->status === 'active', 409, 'This quiz has already been submitted.');

        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['present', 'nullable', 'string', 'max:1000'],
            'response_times' => ['sometimes', 'array'],
            'response_times.*' => ['nullable', 'integer', 'min:0', 'max:3600000'],
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
        foreach ($questions as $question) {
            $answer = (string) ($answers[$question->id] ?? '');
            $analytics->record([
                'user_id' => Auth::id(),
                'role' => Auth::user()->role,
                'session_id' => $session->id,
                'question_id' => $question->id,
                'topic' => $session->topic,
                'language' => $session->language,
                'event_type' => 'answer_submitted',
                'payload' => [
                    'selected_option' => $question->question_type === 'fill_in_the_blank'
                        || in_array($answer, ['', self::TIMEOUT_ANSWER], true)
                        ? null
                        : $answer,
                    'is_correct' => $question->isCorrectAnswer($answer),
                    'timed_out' => $answer === self::TIMEOUT_ANSWER,
                    'response_time_ms' => $validated['response_times'][$question->id] ?? null,
                ],
            ]);
        }
        $analytics->record([
            'user_id' => Auth::id(),
            'role' => Auth::user()->role,
            'session_id' => $session->id,
            'topic' => $session->topic,
            'language' => $session->language,
            'event_type' => 'attempt_completed',
            'payload' => ['score' => $score, 'question_count' => count($questionIds), 'attempt_type' => 'self_study'],
        ]);

        return response()->json([
            'success' => true,
            'score' => $score,
            'total' => count($questionIds),
            'redirect_url' => route('student.results', $session),
        ]);
    }
}
