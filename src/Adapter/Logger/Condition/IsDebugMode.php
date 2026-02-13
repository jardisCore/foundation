<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Condition;

/**
 * Condition: Checks if debug mode is enabled.
 *
 * Returns true if the DEBUG environment variable is set to a truthy value.
 * Recognizes: '1', 'true', 'yes', 'on' (case-insensitive).
 */
final class IsDebugMode
{
    public function __invoke(): bool
    {
        $debug = $_ENV['DEBUG'] ?? $_SERVER['DEBUG'] ?? '';
        $debug = $debug ?: ($_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? '');

        return in_array(strtolower((string) $debug), ['1', 'true', 'yes', 'on'], true);
    }
}
