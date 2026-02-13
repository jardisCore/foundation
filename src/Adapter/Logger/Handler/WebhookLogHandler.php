<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogWebhook;

/**
 * Webhook Log Handler
 *
 * Creates webhook log handler from configuration.
 *
 * Required options: 'url'
 */
class WebhookLogHandler
{
    public function __invoke(LoggerHandlerConfig $config): LogCommandInterface
    {
        $url = $config->getOption('url');
        if ($url === null || !is_string($url) || trim($url) === '') {
            throw new InvalidArgumentException(
                "Webhook handler requires 'url' option. Example: [type=>webhook, url=>https://example.com/log]"
            );
        }

        return new LogWebhook($config->level, $url);
    }
}
