<?php

namespace App\Exceptions;

use RuntimeException;

class QuizProviderConfigurationException extends RuntimeException
{
    public function __construct(public readonly string $providerName)
    {
        parent::__construct('The quiz provider is not configured.');
    }
}
