<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\Handler\SamplingLogHandler;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Handler\LogNull;
use PHPUnit\Framework\TestCase;

class SamplingLogHandlerTest extends TestCase
{
    public function testCreatesPercentageSampling(): void
    {
        $handler = new SamplingLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'test_sampling',
            'level' => 'INFO',
            'strategy' => 'percentage',
            'percentage' => '50',
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testCreatesRateSampling(): void
    {
        $handler = new SamplingLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'test_sampling',
            'level' => 'INFO',
            'strategy' => 'rate',
            'rate' => '100',
            'interval' => '60',
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testCreatesSmartSampling(): void
    {
        $handler = new SamplingLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'test_sampling',
            'level' => 'INFO',
            'strategy' => 'smart',
            'error_rate' => '100',
            'warning_rate' => '50',
            'info_rate' => '10',
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testCreatesFingerprintSampling(): void
    {
        $handler = new SamplingLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'test_sampling',
            'level' => 'INFO',
            'strategy' => 'fingerprint',
            'fingerprint_key' => 'user_id',
            'sample_percentage' => '25',
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testUsesDefaultStrategyWhenNotProvided(): void
    {
        $handler = new SamplingLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'test_sampling',
            'level' => 'INFO',
            // Missing 'strategy' parameter - should default to 'percentage'
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testThrowsExceptionForUnknownStrategy(): void
    {
        $handler = new SamplingLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'test_sampling',
            'level' => 'INFO',
            'strategy' => 'unknown_strategy',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sampling strategy');

        $handler($config, $wrappedHandler);
    }

    public function testPercentageStrategyUsesDefaultWhenParameterMissing(): void
    {
        $handler = new SamplingLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'test_sampling',
            'level' => 'INFO',
            'strategy' => 'percentage',
            // Missing 'percentage' parameter - should default to 10
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testRateStrategyUsesDefaultWhenRateParameterMissing(): void
    {
        $handler = new SamplingLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'test_sampling',
            'level' => 'INFO',
            'strategy' => 'rate',
            // Missing 'rate' parameter - should default to 100
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testFingerprintStrategyUsesDefaultWindowWhenMissing(): void
    {
        $handler = new SamplingLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'test_sampling',
            'level' => 'INFO',
            'strategy' => 'fingerprint',
            'fingerprint_key' => 'user_id',
            // Missing 'window' parameter - should default to 60
        ]);

        $result = $handler($config, $wrappedHandler);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testDefaultsForOptionalParameters(): void
    {
        $handler = new SamplingLogHandler();
        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        // Rate strategy with defaults
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'test_sampling',
            'level' => 'INFO',
            'strategy' => 'rate',
            'rate' => '100',
            'interval' => '60',
            // Optional: window_size defaults to 3600
        ]);

        $result = $handler($config, $wrappedHandler);
        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);

        // Smart strategy with defaults
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'test_sampling',
            'level' => 'INFO',
            'strategy' => 'smart',
            'error_rate' => '100',
            // Optional: warning_rate, info_rate, debug_rate default to 100
        ]);

        $result = $handler($config, $wrappedHandler);
        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }
}
