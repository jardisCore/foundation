<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogTeams;

/**
 * Microsoft Teams Log Handler
 *
 * Creates Microsoft Teams webhook log handler from configuration.
 *
 * Required options: 'webhook'
 * Optional options: 'timeout', 'retry'
 */
class TeamsLogHandler
{
    public function __invoke(LoggerHandlerConfig $config): LogCommandInterface
    {
        $webhook = $config->getOption('webhook');
        if ($webhook === null || !is_string($webhook) || trim($webhook) === '') {
            throw new InvalidArgumentException(
                "Teams handler requires 'webhook' option. " .
                "Example: [type=>teams, webhook=>https://outlook.office.com/webhook/...]"
            );
        }

        $timeout = (int) ($config->getOption('timeout', 10));
        $retry = (int) ($config->getOption('retry', 3));

        return new LogTeams($config->level, $webhook, $timeout, $retry);
    }
}
