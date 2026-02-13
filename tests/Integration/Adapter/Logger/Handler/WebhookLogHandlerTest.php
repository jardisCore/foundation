<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\Handler\WebhookLogHandler;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use PHPUnit\Framework\TestCase;

class WebhookLogHandlerTest extends TestCase
{
    public function testCreatesWebhookLogHandler(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'webhook',
            'name' => 'test_webhook',
            'level' => 'ERROR',
            'url' => 'https://example.com/webhook'
        ]);

        $handler = new WebhookLogHandler();
        $logCommand = $handler->__invoke($config);

        $this->assertInstanceOf(LogCommandInterface::class, $logCommand);
    }

    public function testThrowsExceptionForMissingUrl(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'webhook',
            'name' => 'test_webhook',
            'level' => 'ERROR'
            // Missing 'url'
        ]);

        $handler = new WebhookLogHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('url');

        $handler->__invoke($config);
    }

    public function testThrowsExceptionForEmptyUrl(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'webhook',
            'name' => 'test_webhook',
            'level' => 'ERROR',
            'url' => '   ' // Empty after trim
        ]);

        $handler = new WebhookLogHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('url');

        $handler->__invoke($config);
    }

    public function testThrowsExceptionForNonStringUrl(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'webhook',
            'name' => 'test_webhook',
            'level' => 'ERROR',
            'url' => 12345 // Not a string
        ]);

        $handler = new WebhookLogHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('url');

        $handler->__invoke($config);
    }
}
