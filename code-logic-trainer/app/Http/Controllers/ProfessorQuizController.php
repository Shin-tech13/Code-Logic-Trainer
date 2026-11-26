<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidQuizOutputException;
use App\Exceptions\QuizProviderConfigurationException;
use App\Exceptions\QuizProviderException;
use App\Models\Quiz;
use App\Models\UploadedFile;
use App\Services\DocumentQuizGenerationService;
use App\Services\GeminiQuizService;
use App\Support\QuestionTimer;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProfessorQuizController extends Controller
{
    protected GeminiQuizService $gemini;

    protected DocumentQuizGenerationService $documentQuizGenerator;

    public function __construct(
        GeminiQuizService $gemini,
        DocumentQuizGenerationService $documentQuizGenerator,
    ) {
        $this->gemini = $gemini;
        $this->documentQuizGenerator = $documentQuizGenerator;
    }

    public function index()
    {
        $quizzes = Quiz::where('professor_id', Auth::id())
            ->withCount('questions')
            ->latest()
            ->get();
        $generationRequestId = (string) Str::uuid();

        return view('professor.dashboard', compact('quizzes', 'generationRequestId'));
    }

    // The dashboard submits this upload with fetch and expects JSON.
    public function uploadAndGenerate(Request $request)
    {
        set_time_limit(300);
        $uploaded = null;
        $requestId = (string) Str::uuid();

        try {
            $request->merge([
                'seconds_per_question' => QuestionTimer::normalize($request->input('seconds_per_question')),
            ]);

            $validated = $request->validate([
                'pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
                'title' => ['required', 'string', 'max:255'],
                'count' => ['nullable', 'integer', 'min:0', 'max:150'],
                'description' => ['nullable', 'string', 'max:1000'],
                'quiz_format' => ['required', 'in:multiple_choice,fill_in_the_blank'],
                'seconds_per_question' => ['required', 'integer', 'between:1,100'],
                'idempotency_key' => ['required', 'uuid'],
            ]);

            $cacheKey = 'professor-document-quiz:'.hash(
                'sha256',
                Auth::id().'|'.$validated['idempotency_key'],
            );

            return Cache::lock($cacheKey.':lock', 300)->block(240, function () use (
                $request,
                $validated,
                $cacheKey,
                $requestId,
                &$uploaded,
            ) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    if (($cached['status'] ?? null) === 'complete'
                        && is_array($cached['response'] ?? null)
                        && Quiz::query()
                            ->whereKey($cached['quiz_id'] ?? null)
                            ->where('professor_id', Auth::id())
                            ->exists()) {
                        return response()->json($cached['response']);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => 'This request is already being processed or was completed. Refresh the dashboard before starting another quiz.',
                    ], 409);
                }

                if (! Cache::put($cacheKey, ['status' => 'processing'], now()->addDay())) {
                    throw new RuntimeException('Quiz request deduplication is unavailable.');
                }

                try {
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

                    $uploaded = UploadedFile::create([
                        'professor_id' => Auth::id(),
                        'file_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'status' => 'pending',
                    ]);

                    $text = $this->gemini->extractPdfText(Storage::disk('local')->path($path));
                    $questions = $this->documentQuizGenerator->generate(
                        $text,
                        isset($validated['count']) && (int) $validated['count'] > 0
                            ? (int) $validated['count']
                            : null,
                        $validated['quiz_format'],
                        $requestId,
                    );

                    $quiz = DB::transaction(function () use ($validated, $uploaded, $questions) {
                        $quiz = Quiz::create([
                            'professor_id' => Auth::id(),
                            'title' => $validated['title'],
                            'description' => $validated['description'] ?? null,
                            'source_file_id' => $uploaded->id,
                            'question_count' => count($questions),
                            'seconds_per_question' => $validated['seconds_per_question'],
                            'status' => 'ready',
                            'quiz_format' => $validated['quiz_format'],
                        ]);

                        foreach ($questions as $question) {
                            $quiz->questions()->create($question);
                        }

                        $uploaded->update(['status' => 'processed']);

                        return $quiz;
                    });

                    $responseData = [
                        'success' => true,
                        'quiz_id' => $quiz->id,
                        'question_count' => $quiz->question_count,
                        'quiz_format' => $quiz->quiz_format,
                        'seconds_per_question' => $quiz->seconds_per_question,
                        'redirect_url' => route('professor.quizzes.show', $quiz->id),
                    ];

                    try {
                        $cached = Cache::put($cacheKey, [
                            'status' => 'complete',
                            'quiz_id' => $quiz->id,
                            'response' => $responseData,
                        ], now()->addDay());
                        if (! $cached) {
                            Log::channel('stderr')->error('Quiz generation idempotency result could not be cached.', [
                                'exception_class' => RuntimeException::class,
                                'request_id' => $requestId,
                            ]);
                        }
                    } catch (Throwable $cacheException) {
                        Log::channel('stderr')->error('Quiz generation idempotency result could not be cached.', [
                            'exception_class' => $cacheException::class,
                            'request_id' => $requestId,
                        ]);
                    }

                    return response()->json($responseData);
                } catch (Throwable $exception) {
                    Cache::forget($cacheKey);
                    throw $exception;
                }
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the highlighted upload details.',
                'errors' => $e->errors(),
            ], 422);
        } catch (LockTimeoutException) {
            return response()->json([
                'success' => false,
                'message' => 'This quiz request is still processing. Please wait and try again.',
            ], 409);
        } catch (Throwable $e) {
            $diagnostic = array_filter([
                'provider' => $e instanceof QuizProviderException
                    ? $e->providerName
                    : ($e instanceof QuizProviderConfigurationException || $e instanceof InvalidQuizOutputException
                        ? $e->providerName
                        : null),
                'exception_class' => $e::class,
                'provider_http_status' => $e instanceof QuizProviderException
                    ? $e->httpStatus
                    : null,
                'provider_error_code' => $e instanceof QuizProviderException
                    ? $e->providerErrorCode
                    : null,
                'request_id' => $requestId,
            ], static fn ($value) => $value !== null);
            Log::channel('stderr')->error('PDF quiz generation failed.', $diagnostic);

            if (isset($uploaded)) {
                $uploaded->update(['status' => 'failed']);
            }

            $message = match (true) {
                $e instanceof QuizProviderConfigurationException,
                $e instanceof QuizProviderException && ! $e->transient => 'Quiz generation is not available because a provider is not configured correctly. Please contact an administrator.',
                $e instanceof InvalidQuizOutputException => 'The AI returned quiz data that could not be validated. Try a shorter or clearer PDF.',
                $e instanceof QuizProviderException => 'Quiz generation is temporarily unavailable. Please try again later.',
                default => 'Quiz generation could not be completed. Check the PDF and try again later.',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'request_id' => $requestId,
            ], 502);
        }
    }

    public function show(Quiz $quiz)
    {
        if ($quiz->professor_id !== Auth::id()) {
            abort(403);
        }

        $quiz->load('questions');

        return view('professor.quiz-show', compact('quiz'));
    }

    public function updateSettings(Request $request, Quiz $quiz)
    {
        abort_unless($quiz->professor_id === Auth::id(), 403);

        $request->merge([
            'seconds_per_question' => QuestionTimer::normalize($request->input('seconds_per_question')),
        ]);

        $validated = $request->validate([
            'seconds_per_question' => ['required', 'integer', 'between:1,100'],
        ]);

        $quiz->update([
            'seconds_per_question' => $validated['seconds_per_question'],
        ]);

        return back()->with('status', 'Question timer settings saved.');
    }

    public function destroy(Quiz $quiz)
    {
        if ($quiz->professor_id !== Auth::id()) {
            abort(403);
        }

        $quiz->delete();

        return back()->with('status', 'Quiz deleted.');
    }
}
