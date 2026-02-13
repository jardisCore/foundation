<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Condition;

/**
 * Condition: Checks if the current request is an HTTP POST request.
 *
 * Returns true if $_SERVER['REQUEST_METHOD'] is 'POST'.
 */
final class IsPostRequest
{
    public function __invoke(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }
}
