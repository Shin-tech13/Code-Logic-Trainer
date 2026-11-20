<?php

namespace App\Exceptions;

use RuntimeException;

class QuizProviderException extends RuntimeException
{
    public readonly ?string $providerErrorCode;

    public function __construct(
        public readonly string $providerName,
        public readonly ?int $httpStatus,
        ?string $providerErrorCode,
        public readonly bool $transient,
        public readonly ?int $retryAfterSeconds = null,
        ?\Throwable $previous = null,
    ) {
        $this->providerErrorCode = is_string($providerErrorCode)
            && preg_match('/^[A-Za-z0-9_.-]{1,100}$/', $providerErrorCode) === 1
            ? $providerErrorCode
            : null;

        parent::__construct('The quiz provider could not complete this request.', 0, $previous);
    }
}
