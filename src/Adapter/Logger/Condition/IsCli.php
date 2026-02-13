<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Condition;

/**
 * Condition: Check if running in CLI mode
 *
 * Returns true when PHP is running in command-line interface mode.
 *
 * Example usage in .env:
 * ```
 * LOG_HANDLER1_TYPE=conditional
 * LOG_HANDLER1_CONDITIONS=IsCli
 * ```
 */
class IsCli
{
    /**
     * Check if running in CLI mode.
     *
     * @return bool True if PHP_SAPI is 'cli', false otherwise
     */
    public function __invoke(): bool
    {
        return PHP_SAPI === 'cli';
    }
}
