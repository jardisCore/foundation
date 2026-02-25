<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Condition;

/**
 * Condition: Check if current request is an API request
 *
 * Returns true when the request URI starts with '/api'.
 *
 * Example usage in .env:
 * ```
 * LOG_HANDLER1_TYPE=conditional
 * LOG_HANDLER1_CONDITIONS=IsApiRequest
 * ```
 */
class IsApiRequest
{
    /**
     * Check if current request is an API request.
     *
     * @return bool True if REQUEST_URI starts with '/api', false otherwise
     */
    public function __invoke(): bool
    {
        return isset($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], '/api');
    }
}
