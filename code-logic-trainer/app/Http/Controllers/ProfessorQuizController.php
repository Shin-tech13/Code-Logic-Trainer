<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\UploadedFile;
use App\Services\GeminiQuizService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProfessorQuizController extends Controller
{
    protected GeminiQuizService $gemini;

    public function __construct(GeminiQuizService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Professor dashboard: lists their quizzes.
     */
    public function index()
    {
        $quizzes = Quiz::where('professor_id', Auth::id())
            ->withCount('questions')
            ->latest()
            ->get();

        return view('professor.dashboard', compact('quizzes'));
    }

    /**
     * Upload a PDF and generate a quiz from it via Gemini.
     * Returns JSON for the Alpine/fetch handler.
     */
    public function uploadAndGenerate(Request $request)
    {
        set_time_limit(300);
        $uploaded = null;

        try {
            $validated = $request->validate([
                'pdf'        => ['required', 'file', 'mimes:pdf', 'max:20480'],
                'title'      => ['required', 'string', 'max:255'],
                'count'      => ['nullable', 'integer', 'min:0', 'max:40'],
                'description' => ['nullable', 'string', 'max:1000'],
                'quiz_format' => ['required', 'in:multiple_choice,fill_in_the_blank'],
            ]);

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
                'professor_id'  => Auth::id(),
                'file_path'     => $path,
                'original_name' => $file->getClientOriginalName(),
                'status'        => 'pending',
            ]);

            // Extract text and generate quiz.
            $fullPath  = Storage::disk('local')->path($path);
            $text      = $this->gemini->extractPdfText($fullPath);
            $questions = $this->gemini->generateQuizFromText(
                $text,
                $validated['title'],
                isset($validated['count']) && (int) $validated['count'] > 0
                    ? (int) $validated['count']
                    : null,
                $validated['quiz_format'],
            );

            if (empty($questions)) {
                $uploaded->update(['status' => 'failed']);
                return response()->json([
                    'success' => false,
                    'message' => 'No valid questions could be generated from this PDF. Try a document with more readable course material.',
                ], 422);
            }

            $quiz = DB::transaction(function () use ($validated, $uploaded, $questions) {
                $quiz = Quiz::create([
                    'professor_id'   => Auth::id(),
                    'title'          => $validated['title'],
                    'description'    => $validated['description'] ?? null,
                    'source_file_id' => $uploaded->id,
                    'question_count' => count($questions),
                    'status'         => 'ready',
                    'quiz_format'    => $validated['quiz_format'],
                ]);

                foreach ($questions as $q) {
                    $quiz->questions()->create([
                        'question_text'  => $q['question_text'] ?? '',
                        'options'        => $q['options'] ?? [],
                        'question_type'  => $q['question_type'] ?? 'multiple_choice',
                        'correct_answer' => $q['correct_answer'] ?? '',
                        'accepted_answers' => $q['accepted_answers'] ?? [],
                        'explanation'    => $q['explanation'] ?? '',
                        'needs_review'   => $q['needs_review'] ?? true,
                    ]);
                }

                $uploaded->update(['status' => 'processed']);

                return $quiz;
            });

            return response()->json([
                'success'     => true,
                'quiz_id'     => $quiz->id,
                'question_count' => $quiz->question_count,
                'quiz_format' => $quiz->quiz_format,
                'redirect_url' => route('professor.quizzes.show', $quiz->id),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the highlighted upload details.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('PDF quiz generation failed', [
                'professor_id' => Auth::id(),
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);

            if (isset($uploaded)) {
                $uploaded->update(['status' => 'failed']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Quiz generation could not be completed. Check the PDF and try again later.',
            ], 502);
        }
    }

    /**
     * Show a single quiz and its questions.
     */
    public function show(Quiz $quiz)
    {
        if ($quiz->professor_id !== Auth::id()) {
            abort(403);
        }

        $quiz->load('questions');

        return view('professor.quiz-show', compact('quiz'));
    }

    /**
     * Delete a quiz.
     */
    public function destroy(Quiz $quiz)
    {
        if ($quiz->professor_id !== Auth::id()) {
            abort(403);
        }

        $quiz->delete();

        return back()->with('status', 'Quiz deleted.');
    }
}
