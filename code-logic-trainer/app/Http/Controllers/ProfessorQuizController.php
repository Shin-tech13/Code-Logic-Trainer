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
use Illuminate\Support\Str;
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

        try {
            $request->validate([
                'pdf'        => ['required', 'file', 'mimes:pdf', 'max:20480'],
                'title'      => ['required', 'string', 'max:255'],
                'count'      => ['nullable', 'integer', 'min:1', 'max:30'],
                'description'=> ['nullable', 'string', 'max:1000'],
            ]);

            $file = $request->file('pdf');

            // Persist the uploaded file.
            $path = $file->store('uploads/pdf', 'local');

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
                $request->input('title'),
                (int) ($request->input('count', 10) ?: 10)
            );

            if (empty($questions)) {
                $uploaded->update(['status' => 'failed']);
                return response()->json([
                    'success' => false,
                    'message' => 'Gemini could not generate questions from this PDF. Try a different document.',
                ], 422);
            }

            // Persist quiz + questions atomically.
            $quiz = DB::transaction(function () use ($request, $uploaded, $questions) {
                $quiz = Quiz::create([
                    'professor_id'   => Auth::id(),
                    'title'          => $request->input('title'),
                    'description'    => $request->input('description'),
                    'source_file_id' => $uploaded->id,
                    'question_count' => count($questions),
                    'status'         => 'ready',
                ]);

                foreach ($questions as $q) {
                    if (! is_array($q)) {
                        continue;
                    }
                    $quiz->questions()->create([
                        'question_text'  => $q['question_text'] ?? '',
                        'options'        => $q['options'] ?? [],
                        'correct_answer' => $q['correct_answer'] ?? '',
                        'explanation'    => $q['explanation'] ?? '',
                    ]);
                }

                return $quiz;
            });

            $uploaded->update(['status' => 'processed']);

            return response()->json([
                'success'     => true,
                'quiz_id'     => $quiz->id,
                'question_count' => $quiz->question_count,
                'redirect_url' => route('professor.quizzes.show', $quiz->id),
            ]);
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
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
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
