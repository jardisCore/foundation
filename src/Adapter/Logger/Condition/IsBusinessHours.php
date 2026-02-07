<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Condition;

/**
 * Condition: Check if current time is within business hours
 *
 * Returns true between 9:00 AM and 5:00 PM (17:00).
 *
 * Example usage in .env:
 * ```
 * LOG_HANDLER1_TYPE=conditional
 * LOG_HANDLER1_CONDITIONS=IsBusinessHours
 * ```
 */
class IsBusinessHours
{
    /**
     * Check if current time is within business hours (9 AM - 5 PM).
     *
     * @return bool True if between 9:00 and 17:00, false otherwise
     */
    public function __invoke(): bool
    {
        $hour = (int) date('H');
        return $hour >= 9 && $hour < 17;
    }
}
