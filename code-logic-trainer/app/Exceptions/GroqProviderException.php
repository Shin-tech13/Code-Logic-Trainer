<?php

namespace App\Exceptions;

class GroqProviderException extends QuizProviderException
{
    public function __construct(
        ?int $httpStatus,
        ?string $providerErrorCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            'groq',
            $httpStatus,
            $providerErrorCode,
            $httpStatus === null || in_array($httpStatus, [429, 503], true),
            null,
            $previous,
        );
    }
}
