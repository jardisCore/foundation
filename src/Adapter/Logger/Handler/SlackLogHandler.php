<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogSlack;

/**
 * Slack Log Handler
 *
 * Creates Slack webhook log handler from configuration.
 *
 * Required options: 'webhook'
 * Optional options: 'timeout', 'retry'
 */
class SlackLogHandler
{
    public function __invoke(LoggerHandlerConfig $config): LogCommandInterface
    {
        $webhook = $config->getOption('webhook');
        if ($webhook === null || !is_string($webhook) || trim($webhook) === '') {
            throw new InvalidArgumentException(
                "Slack handler requires 'webhook' option. Example: [type=>slack, webhook=>https://hooks.slack.com/...]"
            );
        }

        $timeout = (int) ($config->getOption('timeout', 10));
        $retry = (int) ($config->getOption('retry', 3));

        return new LogSlack($config->level, $webhook, $timeout, $retry);
    }
}
