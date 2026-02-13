<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Condition;

/**
 * Condition: Checks if the current request is an AJAX/XHR request.
 *
 * Returns true if the HTTP_X_REQUESTED_WITH header is set to 'XMLHttpRequest'.
 */
final class IsAjaxRequest
{
    public function __invoke(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }
}
