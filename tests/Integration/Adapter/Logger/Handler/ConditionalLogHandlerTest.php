<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\Handler\ConditionalLogHandler;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Handler\LogNull;
use PHPUnit\Framework\TestCase;

class ConditionalLogHandlerTest extends TestCase
{
    public function testCreatesConditionalHandlerWithSingleCondition(): void
    {
        $kernel = TestKernelFactory::create();
        $handler = new ConditionalLogHandler();

        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'conditional',
            'name' => 'test_conditional',
            'level' => 'INFO',
            'conditions' => 'AlwaysTrue',
        ]);

        $result = $handler($config, $kernel, [$wrappedHandler]);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testCreatesConditionalHandlerWithAndExpression(): void
    {
        $kernel = TestKernelFactory::create();
        $handler = new ConditionalLogHandler();

        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'conditional',
            'name' => 'test_conditional',
            'level' => 'INFO',
            'conditions' => 'AlwaysTrue and IsCli',
        ]);

        $result = $handler($config, $kernel, [$wrappedHandler]);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testCreatesConditionalHandlerWithOrExpression(): void
    {
        $kernel = TestKernelFactory::create();
        $handler = new ConditionalLogHandler();

        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'conditional',
            'name' => 'test_conditional',
            'level' => 'INFO',
            'conditions' => 'AlwaysTrue or AlwaysFalse',
        ]);

        $result = $handler($config, $kernel, [$wrappedHandler]);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testCreatesConditionalHandlerWithComplexExpression(): void
    {
        $kernel = TestKernelFactory::create();
        $handler = new ConditionalLogHandler();

        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'conditional',
            'name' => 'test_conditional',
            'level' => 'INFO',
            'conditions' => 'IsCli and AlwaysTrue or IsWeekend',
        ]);

        $result = $handler($config, $kernel, [$wrappedHandler]);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testSupportsMultipleWrappedHandlers(): void
    {
        $kernel = TestKernelFactory::create();
        $handler = new ConditionalLogHandler();

        $wrappedHandler1 = new LogNull('wrapped1', 'DEBUG');
        $wrappedHandler2 = new LogNull('wrapped2', 'INFO');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'conditional',
            'name' => 'test_conditional',
            'level' => 'INFO',
            'conditions' => 'AlwaysTrue',
        ]);

        $result = $handler($config, $kernel, [$wrappedHandler1, $wrappedHandler2]);

        $this->assertInstanceOf(StreamableLogCommandInterface::class, $result);
    }

    public function testThrowsExceptionWhenConditionsNotProvided(): void
    {
        $kernel = TestKernelFactory::create();
        $handler = new ConditionalLogHandler();

        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'conditional',
            'name' => 'test_conditional',
            'level' => 'INFO',
            // Missing 'conditions' parameter
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('conditions');

        $handler($config, $kernel, [$wrappedHandler]);
    }

    public function testThrowsExceptionForUnknownConditionClass(): void
    {
        $kernel = TestKernelFactory::create();
        $handler = new ConditionalLogHandler();

        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'conditional',
            'name' => 'test_conditional',
            'level' => 'INFO',
            'conditions' => 'NonExistentCondition',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Condition class.*not found/');

        $handler($config, $kernel, [$wrappedHandler]);
    }

    public function testThrowsExceptionWhenNoWrappedHandlersProvided(): void
    {
        $kernel = TestKernelFactory::create();
        $handler = new ConditionalLogHandler();

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'conditional',
            'name' => 'test_conditional',
            'level' => 'INFO',
            'conditions' => 'AlwaysTrue',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one wrapped handler');

        $handler($config, $kernel, []);
    }

    public function testThrowsExceptionForEmptyConditionsString(): void
    {
        $kernel = TestKernelFactory::create();
        $handler = new ConditionalLogHandler();

        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'conditional',
            'name' => 'test_conditional',
            'level' => 'INFO',
            'conditions' => '   ', // Empty after trim
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('conditions');

        $handler($config, $kernel, [$wrappedHandler]);
    }

    public function testThrowsExceptionForNonStringConditions(): void
    {
        $kernel = TestKernelFactory::create();
        $handler = new ConditionalLogHandler();

        $wrappedHandler = new LogNull('wrapped', 'DEBUG');

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'conditional',
            'name' => 'test_conditional',
            'level' => 'INFO',
            'conditions' => ['array', 'value'], // Should be string
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a string');

        $handler($config, $kernel, [$wrappedHandler]);
    }

    public function testThrowsExceptionForNonStreamableWrappedHandler(): void
    {
        $kernel = TestKernelFactory::create();
        $handler = new ConditionalLogHandler();

        // Create a mock that implements LogCommandInterface but NOT StreamableLogCommandInterface
        $nonStreamableHandler = $this->createMock(\JardisAdapter\Logger\Contract\LogCommandInterface::class);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'conditional',
            'name' => 'test_conditional',
            'level' => 'INFO',
            'conditions' => 'AlwaysTrue',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('StreamableLogCommandInterface');

        $handler($config, $kernel, [$nonStreamableHandler]);
    }
}
