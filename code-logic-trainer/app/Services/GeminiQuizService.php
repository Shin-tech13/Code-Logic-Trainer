<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Handles all Gemini API interactions for the professor workflow:
 *  1. PDF text extraction (via Gemini's file/content understanding).
 *  2. Structured MCQ quiz JSON generation from extracted text.
 *
 * If a local PDF text extractor is available it is used first, so text
 * extraction still works without a Gemini key; quiz generation always
 * requires the Gemini key.
 */
class GeminiQuizService
{
    protected ?string $apiKey;
    protected string  $model;
    protected string  $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
        $this->model  = config('services.gemini.model')   ?: env('GEMINI_MODEL', 'gemini-2.0-flash');
        $this->apiUrl = rtrim(
            config('services.gemini.api_url') ?: env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models'),
            '/'
        );
    }

    /**
     * Extract raw text from a PDF file path.
     */
    public function extractPdfText(string $filePath): string
    {
        // Prefer a native extractor so a Gemini key is not strictly required.
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf    = $parser->parseFile($filePath);
                $text   = trim($pdf->getText());

                if ($text !== '') {
                    return $this->cleanExtractedText($text);
                }
            } catch (\Throwable $e) {
                Log::warning('Native PDF extraction failed, falling back to Gemini', [
                    'file'  => $filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: send the PDF to Gemini for text extraction.
        return $this->extractPdfTextViaGemini($filePath);
    }

    /**
     * Extract text by sending the PDF bytes to Gemini.
     */
    protected function extractPdfTextViaGemini(string $filePath): string
    {
        $this->ensureApiKey();

        $base64 = base64_encode((string) file_get_contents($filePath));

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'inlineData' => [
                                'mimeType' => 'application/pdf',
                                'data'     => $base64,
                            ],
                        ],
                        ['text' => 'Extract all the readable text from this PDF. Return only the raw extracted text, with no commentary, no markdown, and no preamble.'],
                    ],
                ],
            ],
        ];

        $text = $this->request('generateContent', $payload);

        if ($text === '') {
            throw new RuntimeException('Gemini could not extract any text from this PDF.');
        }

        return $this->cleanExtractedText($text);
    }

    /**
     * Generate a structured MCQ quiz from the given source text.
     *
     * @return array<int, array{question_text: string, options: array<int,string>, correct_answer: string, explanation: string}>
     */
    public function generateQuizFromText(string $sourceText, string $title, int $count = 10): array
    {
        $this->ensureApiKey();

        $count = max(1, min(30, $count));

        // Cap the source text to avoid oversized requests.
        $sourceText = mb_substr(trim($sourceText), 0, 60000);

        $prompt = <<<PROMPT
Act as an expert computer science professor.

You are given the following study material (extracted from a PDF):

--- BEGIN SOURCE MATERIAL ---
{$sourceText}
--- END SOURCE MATERIAL ---

Create exactly {$count} multiple-choice questions that test the material above.
The quiz title is: "{$title}".

Requirements:
1. Mix of conceptual understanding and code-tracing where applicable.
2. Every question must have exactly 4 choices in the options list, labeled A., B., C., D.
3. "correct_answer" must be an EXACT copy of one of the 4 options (including the "A. " prefix).
4. "explanation" must state why the correct answer is right and why common traps are wrong.
5. Respond with a single JSON array ONLY. No markdown fences. No intro text. No trailing text.

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

        $raw = $this->request('generateContent', [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.7,
                'maxOutputTokens' => 8192,
            ],
        ]);

        return $this->parseJsonArray($raw);
    }

    /**
     * Perform a Gemini generateContent request and return the text part.
     */
    protected function request(string $action, array $payload): string
    {
        $url = "{$this->apiUrl}/{$this->model}:{$action}";

        try {
            $response = Http::timeout(120)
                ->retry(2, 1000, throw: false)
                ->withQueryParameters(['key' => $this->apiKey])
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::error('Gemini network error: ' . $e->getMessage());
            throw new RuntimeException('Could not reach Gemini API (network/timeout): ' . $e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            $body = substr($response->body(), 0, 800);
            Log::error('Gemini API error', ['status' => $response->status(), 'body' => $body]);
            throw new RuntimeException("Gemini API returned HTTP {$response->status()}: {$body}");
        }

        // Gemini returns candidates[0].content.parts[].text
        $text = (string) $response->json('candidates.0.content.parts.0.text', '');

        if ($text === '') {
            $finishReason = $response->json('candidates.0.finishReason');
            $blockReason  = $response->json('promptFeedback.blockReason');
            Log::error('Gemini returned empty content', [
                'finish_reason' => $finishReason,
                'block_reason'  => $blockReason,
            ]);
            throw new RuntimeException('Gemini returned an empty response. Finish reason: ' . ($finishReason ?: 'unknown'));
        }

        return $text;
    }

    /**
     * Parse a model response into the expected question array.
     */
    protected function parseJsonArray(string $raw): array
    {
        $raw = trim($raw);

        // Strip markdown code fences if the model adds them anyway.
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);

        $start = strpos($raw, '[');
        $end   = strrpos($raw, ']');

        if ($start === false || $end === false || $end < $start) {
            Log::error('Gemini response has no JSON array', ['raw' => mb_substr($raw, 0, 1000)]);
            throw new RuntimeException('Gemini did not return a valid JSON array.');
        }

        $json    = substr($raw, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Gemini JSON parse failed', [
                'error' => json_last_error_msg(),
                'json'  => mb_substr($json, 0, 1000),
            ]);
            throw new RuntimeException('Gemini returned invalid JSON: ' . json_last_error_msg());
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    protected function cleanExtractedText(string $text): string
    {
        // Normalize whitespace and strip excessive blank lines.
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    protected function ensureApiKey(): void
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('GEMINI_API_KEY is not configured. Add it to your .env file.');
        }
    }
}

