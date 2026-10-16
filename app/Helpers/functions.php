<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /** Escape a value for safe insertion into HTML text or a quoted attribute. */
    function e(mixed $value): string
    {
        if (!is_scalar($value) && !$value instanceof Stringable) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
