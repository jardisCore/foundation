<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Condition;

/**
 * Condition: Checks if the request comes from localhost.
 *
 * Returns true if REMOTE_ADDR is 127.0.0.1, ::1, or localhost.
 */
final class IsLocalhost
{
    public function __invoke(): bool
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

        return in_array($remoteAddr, ['127.0.0.1', '::1', 'localhost'], true);
    }
}
