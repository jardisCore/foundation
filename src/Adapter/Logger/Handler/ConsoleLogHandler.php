<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogConsole;

/**
 * Console Log Handler
 *
 * Creates console log handler from configuration.
 */
class ConsoleLogHandler
{
    public function __invoke(LoggerHandlerConfig $config): LogCommandInterface
    {
        return new LogConsole($config->level);
    }
}
