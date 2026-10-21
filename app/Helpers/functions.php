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

if (!function_exists('app_locale')) {
    function app_locale(): string
    {
        $locale = $_SESSION['locale'] ?? 'en';
        return in_array($locale, ['en', 'km'], true) ? $locale : 'en';
    }
}

if (!function_exists('t')) {
    function t(string $text, array $values = []): string
    {
        static $translations = [];
        $locale = app_locale();
        if ($locale !== 'en' && !isset($translations[$locale])) {
            $path = dirname(__DIR__, 2) . '/resources/lang/' . $locale . '.php';
            $translations[$locale] = is_file($path) ? require $path : [];
        }
        $translated = $locale === 'en' ? $text : ($translations[$locale][$text] ?? $text);
        foreach ($values as $key => $value) {
            $translated = str_replace('{' . $key . '}', (string) $value, $translated);
        }
        return $translated;
    }
}

if (!function_exists('app_translations')) {
    /** @return array<string, string> */
    function app_translations(): array
    {
        $locale = app_locale();
        if ($locale === 'en') {
            return [];
        }
        $path = dirname(__DIR__, 2) . '/resources/lang/' . $locale . '.php';
        $translations = is_file($path) ? require $path : [];
        return is_array($translations) ? $translations : [];
    }
}
