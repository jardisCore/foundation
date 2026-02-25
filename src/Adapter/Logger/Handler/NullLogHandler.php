<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogNull;

/**
 * Null Log Handler
 *
 * Creates null log handler from configuration (discards all logs).
 */
class NullLogHandler
{
    public function __invoke(LoggerHandlerConfig $config): LogCommandInterface
    {
        return new LogNull($config->level);
    }
}
