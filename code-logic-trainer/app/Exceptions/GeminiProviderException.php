<?php

namespace App\Exceptions;

use RuntimeException;

class GeminiProviderException extends RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly ?string $providerErrorCode,
    ) {
        parent::__construct('The quiz service could not complete this request.');
    }
}
