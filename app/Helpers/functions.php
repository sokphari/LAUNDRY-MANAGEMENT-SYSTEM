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

if (!function_exists('format_khr')) {
    /** Format KHR amounts without unnecessary trailing .00. */
    function format_khr(mixed $value): string
    {
        $formatted = number_format((float) $value, 2, '.', ',');
        return str_ends_with($formatted, '.00') ? substr($formatted, 0, -3) : $formatted;
    }
}
