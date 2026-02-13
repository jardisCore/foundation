<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Condition;

/**
 * Condition: Check if today is weekend
 *
 * Returns true on Saturday (6) or Sunday (7).
 *
 * Example usage in .env:
 * ```
 * LOG_HANDLER1_TYPE=conditional
 * LOG_HANDLER1_CONDITIONS=IsWeekend
 * ```
 */
class IsWeekend
{
    /**
     * Check if today is weekend.
     *
     * @return bool True if Saturday or Sunday, false otherwise
     */
    public function __invoke(): bool
    {
        return in_array((int) date('N'), [6, 7], true);
    }
}
