<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class InvalidOrderException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The order contains invalid information.');
    }
}
