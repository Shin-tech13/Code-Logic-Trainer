<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidQuizOutputException extends RuntimeException
{
    public function __construct(
        public readonly string $providerName,
        public readonly string $reasonCode = 'invalid_output',
    ) {
        parent::__construct('The quiz provider returned invalid quiz data.');
    }
}
