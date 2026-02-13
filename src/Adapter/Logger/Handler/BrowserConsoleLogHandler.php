<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogBrowserConsole;

/**
 * Browser Console Log Handler
 *
 * Creates browser console log handler from configuration.
 */
class BrowserConsoleLogHandler
{
    public function __invoke(LoggerHandlerConfig $config): LogCommandInterface
    {
        return new LogBrowserConsole($config->level);
    }
}
