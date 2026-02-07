<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Condition;

/**
 * Condition: Checks if the current request expects JSON response.
 *
 * Returns true if the Content-Type or Accept header contains 'application/json'.
 */
final class IsJsonRequest
{
    public function __invoke(): bool
    {
        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');

        return str_contains($contentType, 'application/json')
            || str_contains($accept, 'application/json');
    }
}
