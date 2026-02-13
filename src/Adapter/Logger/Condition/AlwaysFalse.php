<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Condition;

/**
 * Condition: Always returns false
 *
 * Helper condition that always evaluates to false.
 * Useful for testing or disabling handlers temporarily.
 *
 * Example usage in .env:
 * ```
 * LOG_HANDLER1_TYPE=conditional
 * LOG_HANDLER1_CONDITIONS=AlwaysFalse
 * ```
 */
class AlwaysFalse
{
    /**
     * Always returns false.
     *
     * @return bool Always false
     */
    public function __invoke(): bool
    {
        return false;
    }
}
