<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class QuizGeneratorService
{
    protected ?string $apiKey;
    protected string  $model;
    protected string  $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key') ?: env('GROQ_API_KEY');
        $this->model  = config('services.groq.model')   ?: env('GROQ_MODEL', 'llama-3.3-70b-versatile');
    }

    public function generateStudentQuiz(string $language, string $topic, int $count = 10): array
    {
        $count = max(5, min(20, $count));

        if (empty($this->apiKey)) {
            throw new RuntimeException('GROQ_API_KEY is not configured. Add it to your .env file.');
        }

        $prompt = <<<PROMPT
Act as an expert computer science professor and interactive coding tutor.

Generate exactly {$count} multiple-choice questions testing code logic, tracing, and conceptual understanding.

Target Parameters:
- Language: {$language}
- Fundamental Topic: {$topic}

Requirements:
1. Mix of code-tracing problems and core logical concepts.
2. Every question must have exactly 4 choices in the options list.
3. Explanation must state why the correct answer is right and why common traps are wrong.
4. Respond with a single JSON array ONLY. No markdown fences. No intro text. No trailing text.

Required Output Format (strict JSON array, nothing else):
[
  {
    "question_text": "...",
    "options": ["A. ...", "B. ...", "C. ...", "D. ..."],
    "correct_answer": "A. ...",
    "explanation": "..."
  }
]
PROMPT;

        try {
            $response = Http::timeout(60)
                ->retry(2, 1000, throw: false)
                ->withToken($this->apiKey)           // Groq uses Bearer token auth
                ->post($this->apiUrl, [
                    'model'       => $this->model,
                    'temperature' => 0.7,
                    'max_tokens'  => 8192,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => 'You are a computer science quiz generator. You respond only with valid JSON arrays. No markdown, no explanation outside the JSON.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::error('Groq network error: ' . $e->getMessage());
            throw new RuntimeException('Could not reach Groq API (network/timeout): ' . $e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            $body = substr($response->body(), 0, 800);
            Log::error('Groq API error', ['status' => $response->status(), 'body' => $body]);
            throw new RuntimeException("Groq API returned HTTP {$response->status()}: {$body}");
        }

        // Groq response: choices[0].message.content
        $rawText = (string) $response->json('choices.0.message.content', '');

        if ($rawText === '') {
            $finishReason = $response->json('choices.0.finish_reason');
            Log::error('Groq returned empty content', [
                'finish_reason' => $finishReason,
                'full_response' => $response->json(),
            ]);
            throw new RuntimeException('Groq returned an empty response. Finish reason: ' . ($finishReason ?? 'unknown'));
        }

        // Strip markdown code fences if the model adds them anyway
        $rawText = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
        $rawText = preg_replace('/\s*```$/', '', $rawText);

        // Extract the JSON array
        $start = strpos($rawText, '[');
        $end   = strrpos($rawText, ']');

        if ($start === false || $end === false || $end < $start) {
            Log::error('Groq response has no JSON array', ['raw' => substr($rawText, 0, 1000)]);
            throw new RuntimeException('Groq did not return a valid JSON array.');
        }

        $json    = substr($rawText, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Groq JSON parse failed', [
                'error' => json_last_error_msg(),
                'json'  => substr($json, 0, 1000),
            ]);
            throw new RuntimeException('Groq returned invalid JSON: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
