<?php

namespace App\Http\Controllers;

use App\Models\ChallengeCompletion;
use App\Models\CodeChallenge;
use App\Services\CodeChallengeRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CodeChallengeController extends Controller
{
    public function index()
    {
        $challenges = CodeChallenge::query()
            ->where('is_active', true)
            ->withCount([
                'completions as completed_by_user' => fn ($query) => $query->where('user_id', Auth::id()),
            ])
            ->orderBy('id')
            ->get();

        return view('student.challenges.index', compact('challenges'));
    }

    public function show(CodeChallenge $challenge)
    {
        abort_unless($challenge->is_active, 404);

        $completed = $challenge->completions()
            ->where('user_id', Auth::id())
            ->exists();

        return view('student.challenges.show', compact('challenge', 'completed'));
    }

    public function run(Request $request, CodeChallenge $challenge, CodeChallengeRunner $runner): JsonResponse
    {
        abort_unless($challenge->is_active, 404);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:12000'],
        ]);

        if (strlen($validated['code']) > 12000) {
            return response()->json(['message' => 'Code must be 12,000 bytes or fewer.'], 422);
        }

        if (count($challenge->test_cases ?? []) < 1 || count($challenge->test_cases) > 30) {
            Log::error('Code challenge has an invalid test-case configuration.', [
                'challenge_id' => $challenge->id,
            ]);

            return response()->json(['message' => 'This challenge is temporarily unavailable.'], 503);
        }

        $tests = array_map(
            static fn (array $test, int $index) => [
                'label' => (string) ($test['label'] ?? 'Test '.($index + 1)),
                'arguments' => $test['arguments'],
                'expected' => $test['expected'],
            ],
            $challenge->test_cases,
            array_keys($challenge->test_cases)
        );

        try {
            $result = $runner->run(
                $validated['code'],
                $challenge->function_name,
                $tests
            );
        } catch (Throwable $exception) {
            Log::warning('Code challenge execution failed.', [
                'challenge_id' => $challenge->id,
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            $status = $exception instanceof RuntimeException
                && $exception->getMessage() === 'The code execution runner is not configured.'
                    ? 503
                    : 502;

            return response()->json([
                'message' => $status === 503
                    ? 'Code execution is not available right now.'
                    : 'The code runner could not complete this attempt. Please try again.',
            ], $status);
        }

        $results = array_slice($result['results'], 0, count($tests));
        $passed = $result['passed']
            && count($results) === count($tests)
            && collect($results)->every(fn ($test) => ($test['passed'] ?? false) === true);
        $newBadge = false;

        if ($passed) {
            $completion = ChallengeCompletion::firstOrCreate(
                [
                    'user_id' => Auth::id(),
                    'code_challenge_id' => $challenge->id,
                ],
                [
                    'passed_tests' => count($tests),
                    'total_tests' => count($tests),
                    'completed_at' => now(),
                ]
            );
            $newBadge = $completion->wasRecentlyCreated;
        }

        return response()->json([
            'passed' => $passed,
            'results' => collect($tests)->map(
                fn ($test, $index) => [
                    'label' => $test['label'],
                    'passed' => ($results[$index]['passed'] ?? false) === true,
                ]
            )->values(),
            'output' => Str::limit(strip_tags($result['output']), 2000, ''),
            'badge' => $passed ? $challenge->badge_name : null,
            'badge_unlocked' => $newBadge,
        ]);
    }
}
