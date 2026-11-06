<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class QuizGeneratorService
{
    protected ?string $apiKey;
    protected string  $model;
    protected string  $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key') ?: env('GROQ_API_KEY');
        $this->model  = config('services.groq.model')   ?: env('GROQ_MODEL', 'openai/gpt-oss-120b');
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
4. Respond with one JSON object only, with the questions in a "questions" array. No markdown fences, intro text, or trailing text.

Required Output Format (strict JSON object):
{"questions":[{"question_text":"...","options":["A. ...","B. ...","C. ...","D. ..."],"correct_answer":"A. ...","explanation":"..."}]}
PROMPT;

        try {
            $response = Http::timeout(60)
                ->retry(2, 1000, throw: false)
                ->withToken($this->apiKey)
                ->post($this->apiUrl, [
                    'model'       => $this->model,
                    'temperature' => 0.7,
                    'max_tokens'  => 8192,
                    'response_format' => ['type' => 'json_object'],
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => 'You are a computer science quiz generator. Return only a valid JSON object with a "questions" array. Do not include markdown or text outside the JSON object.',
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

            if ($response->json('error.code') === 'model_not_found') {
                throw new RuntimeException(
                    'Groq rejected the configured model. Set GROQ_MODEL in Render to an active model available to your Groq account, then redeploy.'
                );
            }

            throw new RuntimeException("Groq API returned HTTP {$response->status()}: {$body}");
        }

        $finishReason = $response->json('choices.0.finish_reason');
        if ($finishReason === 'length') {
            throw new RuntimeException('Groq stopped before completing the quiz JSON. Please retry the quiz generation.');
        }

        $rawText = $response->json('choices.0.message.content');

        if (! is_string($rawText) || trim($rawText) === '') {
            Log::error('Groq returned empty content', [
                'finish_reason' => $finishReason,
            ]);
            throw new RuntimeException('Groq returned an empty response. Finish reason: ' . ($finishReason ?? 'unknown'));
        }

        $json = trim($rawText);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $json, $matches) === 1) {
            $json = trim($matches[1]);
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::error('Groq JSON parse failed', [
                'error' => $e->getMessage(),
                'json' => substr($json, 0, 1000),
            ]);
            throw new RuntimeException('Groq returned invalid JSON: ' . $e->getMessage(), 0, $e);
        }

        if (! is_array($decoded) || ! isset($decoded['questions']) || ! is_array($decoded['questions'])) {
            Log::error('Groq JSON response has an invalid structure', [
                'json' => substr($json, 0, 1000),
            ]);
            throw new RuntimeException('Groq returned JSON without a questions array.');
        }

        $questions = $decoded['questions'];
        if (count($questions) !== $count) {
            throw new RuntimeException("Groq returned " . count($questions) . " questions; {$count} were requested. Please retry.");
        }

        foreach ($questions as $index => $question) {
            if (! is_array($question)
                || ! is_string($question['question_text'] ?? null)
                || trim($question['question_text']) === ''
                || ! is_array($question['options'] ?? null)
                || count($question['options']) !== 4
                || count(array_filter($question['options'], static fn ($option) => is_string($option) && trim($option) !== '')) !== 4
                || ! is_string($question['correct_answer'] ?? null)
                || ! in_array(trim($question['correct_answer']), array_map(
                    static fn ($option) => is_string($option) ? trim($option) : '',
                    $question['options']
                ), true)
                || ! is_string($question['explanation'] ?? null)
                || trim($question['explanation']) === '') {
                Log::error('Groq returned an invalid quiz question', ['question_index' => $index]);
                throw new RuntimeException('Groq returned a malformed question. Please retry quiz generation.');
            }
        }

        return $questions;
    }
}
