<?php

namespace App\Support;

final class QuestionTimer
{
    public const DEFAULT_SECONDS = 20;
    public const MAX_SECONDS = 100;

    public static function normalize(mixed $value): int
    {
        if ($value === null || $value === '' || ! is_scalar($value)) {
            return self::DEFAULT_SECONDS;
        }

        $seconds = (int) $value;
        if ($seconds <= 0) {
            return self::DEFAULT_SECONDS;
        }

        return max(1, min(self::MAX_SECONDS, $seconds));
    }
}
