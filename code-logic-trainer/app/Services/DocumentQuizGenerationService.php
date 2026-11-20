<?php

namespace App\Services;

use App\Exceptions\GeminiProviderException;
use Illuminate\Support\Facades\Log;

class DocumentQuizGenerationService
{
    public function __construct(
        protected GeminiQuizService $gemini,
        protected QuizGeneratorService $groq,
    ) {}

    /**
     * @return array<int, array{question_text: string, options: array<int,string>, question_type: string, correct_answer: string, accepted_answers: array<int,string>, explanation: string, needs_review: bool}>
     */
    public function generate(
        string $sourceText,
        ?int $count,
        string $format,
        string $requestId,
    ): array {
        try {
            return $this->gemini->generateQuizFromText($sourceText, '', $count, $format);
        } catch (GeminiProviderException $exception) {
            if (! $exception->transient) {
                throw $exception;
            }

            Log::channel('stderr')->warning('Gemini document quiz generation unavailable; attempting Groq fallback.', [
                'provider' => $exception->providerName,
                'exception_class' => $exception::class,
                'provider_http_status' => $exception->httpStatus,
                'provider_error_code' => $exception->providerErrorCode,
                'request_id' => $requestId,
            ]);

            return $this->groq->generateDocumentQuizFromText($sourceText, $count, $format);
        }
    }
}
