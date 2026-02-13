<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Condition;

/**
 * Condition: Always returns true
 *
 * Helper condition that always evaluates to true.
 * Useful for testing or default cases.
 *
 * Example usage in .env:
 * ```
 * LOG_HANDLER1_TYPE=conditional
 * LOG_HANDLER1_CONDITIONS=AlwaysTrue
 * ```
 */
class AlwaysTrue
{
    /**
     * Always returns true.
     *
     * @return bool Always true
     */
    public function __invoke(): bool
    {
        return true;
    }
}
