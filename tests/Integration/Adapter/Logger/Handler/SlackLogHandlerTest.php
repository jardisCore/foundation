<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\Handler\SlackLogHandler;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use PHPUnit\Framework\TestCase;

class SlackLogHandlerTest extends TestCase
{
    public function testCreatesSlackLogHandler(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'slack',
            'name' => 'test_slack',
            'level' => 'ERROR',
            'webhook' => 'https://hooks.slack.com/services/TEST'
        ]);

        $handler = new SlackLogHandler();
        $logCommand = $handler->__invoke($config);

        $this->assertInstanceOf(LogCommandInterface::class, $logCommand);
    }

    public function testThrowsExceptionForMissingWebhook(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'slack',
            'name' => 'test_slack',
            'level' => 'ERROR'
            // Missing 'webhook'
        ]);

        $handler = new SlackLogHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('webhook');

        $handler->__invoke($config);
    }

    public function testThrowsExceptionForEmptyWebhook(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'slack',
            'name' => 'test_slack',
            'level' => 'ERROR',
            'webhook' => '   ' // Empty after trim
        ]);

        $handler = new SlackLogHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('webhook');

        $handler->__invoke($config);
    }
}
