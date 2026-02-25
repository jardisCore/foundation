<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogErrorLog;

/**
 * Error Log Handler
 *
 * Creates error_log log handler from configuration.
 */
class ErrorLogHandler
{
    public function __invoke(LoggerHandlerConfig $config): LogCommandInterface
    {
        return new LogErrorLog($config->level);
    }
}
