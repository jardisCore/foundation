<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogLoki;

/**
 * Grafana Loki Log Handler
 *
 * Creates Grafana Loki log handler from configuration.
 *
 * Required options: 'url'
 * Optional options: 'labels' (array)
 */
class LokiLogHandler
{
    public function __invoke(LoggerHandlerConfig $config): LogCommandInterface
    {
        $url = $config->getOption('url');
        if ($url === null || !is_string($url) || trim($url) === '') {
            throw new InvalidArgumentException(
                "Loki handler requires 'url' option. Example: [type=>loki, url=>http://loki:3100/loki/api/v1/push]"
            );
        }

        $labels = $config->getOption('labels', []);
        if (!is_array($labels)) {
            $labels = [];
        }

        return new LogLoki($config->level, $url, $labels);
    }
}
