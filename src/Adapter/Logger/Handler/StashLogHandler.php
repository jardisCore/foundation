<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogStash;

/**
 * Logstash Log Handler
 *
 * Creates Logstash log handler from configuration.
 *
 * Required options: 'host', 'port'
 */
class StashLogHandler
{
    public function __invoke(LoggerHandlerConfig $config): LogCommandInterface
    {
        $host = $config->getOption('host');
        $port = $config->getOption('port');

        if ($host === null || !is_string($host) || trim($host) === '') {
            throw new InvalidArgumentException(
                "Logstash handler requires 'host' option"
            );
        }

        if ($port === null) {
            throw new InvalidArgumentException(
                "Logstash handler requires 'port' option. Example: [type=>stash, host=>logstash.local, port=>5000]"
            );
        }

        return new LogStash($config->level, $host, (int) $port);
    }
}
