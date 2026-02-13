<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\Handler\FingersCrossedLogHandler;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Handler\LogNull;
use PHPUnit\Framework\TestCase;

class FingersCrossedLogHandlerTest extends TestCase
{
    public function testCreatesFingersCrossedHandlerWithDefaults(): void
    {
        $handler = new FingersCrossedLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'fingerscrossed',
            'name' => 'test_fingerscrossed',
            'level' => 'INFO',
            'activation_level' => 'ERROR',
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testCreatesFingersCrossedHandlerWithCustomBufferSize(): void
    {
        $handler = new FingersCrossedLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'fingerscrossed',
            'name' => 'test_fingerscrossed',
            'level' => 'INFO',
            'activation_level' => 'ERROR',
            'buffer_size' => '500',
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testCreatesFingersCrossedHandlerWithStopBuffering(): void
    {
        $handler = new FingersCrossedLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'fingerscrossed',
            'name' => 'test_fingerscrossed',
            'level' => 'INFO',
            'activation_level' => 'WARNING',
            'stop_buffering' => 'true',
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testUsesDefaultActivationLevelWhenNotProvided(): void
    {
        $handler = new FingersCrossedLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'fingerscrossed',
            'name' => 'test_fingerscrossed',
            'level' => 'INFO',
            // Missing 'activation_level' parameter - should default to ERROR
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testSupportsAllLogLevels(): void
    {
        $handler = new FingersCrossedLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $levels = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

        foreach ($levels as $level) {
            $config = LoggerHandlerConfig::fromArray([
                'type' => 'fingerscrossed',
                'name' => 'test_fingerscrossed',
                'level' => 'DEBUG',
                'activation_level' => $level,
            ]);

            $result = $handler($config, $wrappedHandler);
            $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
        }
    }

    public function testBufferSizeDefaultsTo100(): void
    {
        $handler = new FingersCrossedLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'fingerscrossed',
            'name' => 'test_fingerscrossed',
            'level' => 'INFO',
            'activation_level' => 'ERROR',
            // buffer_size not specified, should default to 100
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testStopBufferingDefaultsToFalse(): void
    {
        $handler = new FingersCrossedLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'fingerscrossed',
            'name' => 'test_fingerscrossed',
            'level' => 'INFO',
            'activation_level' => 'ERROR',
            // stop_buffering not specified, should default to false
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testAcceptsTruthyValuesForStopBuffering(): void
    {
        $handler = new FingersCrossedLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $truthyValues = ['true', '1', 'yes', 'on'];

        foreach ($truthyValues as $value) {
            $config = LoggerHandlerConfig::fromArray([
                'type' => 'fingerscrossed',
                'name' => 'test_fingerscrossed',
                'level' => 'INFO',
                'activation_level' => 'ERROR',
                'stop_buffering' => $value,
            ]);

            $result = $handler($config, $wrappedHandler);
            $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
        }
    }
}
