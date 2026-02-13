<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogSyslog;

/**
 * Syslog Log Handler
 *
 * Creates syslog log handler from configuration.
 *
 * Optional options: 'facility' (default: LOG_USER)
 */
class SyslogLogHandler
{
    public function __invoke(LoggerHandlerConfig $config): LogCommandInterface
    {
        // Note: LogSyslog constructor only takes logLevel
        // Facility configuration would need to be handled differently
        // (openlog is called internally with hardcoded LOG_USER)
        return new LogSyslog($config->level);
    }
}
