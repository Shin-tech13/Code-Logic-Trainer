<?php

namespace App\Exceptions;

class GeminiProviderException extends QuizProviderException
{
    public function __construct(
        ?int $httpStatus,
        ?string $providerErrorCode,
        ?int $retryAfterSeconds = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            'gemini',
            $httpStatus,
            $providerErrorCode,
            $httpStatus === null || in_array($httpStatus, [429, 503], true),
            $retryAfterSeconds,
            $previous,
        );
    }
}
