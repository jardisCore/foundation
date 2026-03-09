<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger;

use Exception;
use JardisCore\Foundation\Adapter\ConnectionProvider;
use JardisCore\Foundation\Adapter\Logger\InitLogger;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Integration Tests for InitLogger with Wrapper Handlers
 *
 * Tests wrapper handler integration with real configuration.
 */
class InitLoggerWrapperTest extends TestCase
{
    private function createLoggerFromEnv(array $env): ?LoggerInterface
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, $env);
        $connections = new ConnectionProvider();

        return (new InitLogger())($kernel, $connections);
    }

    public function testInitializesSamplingWrapperHandler(): void
    {
        $logger = $this->createLoggerFromEnv([
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'base_handler',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'LOG_HANDLER2_TYPE' => 'sampling',
            'LOG_HANDLER2_NAME' => 'sampling_handler',
            'LOG_HANDLER2_LEVEL' => 'INFO',
            'LOG_HANDLER2_WRAPS' => 'base_handler',
            'LOG_HANDLER2_STRATEGY' => 'percentage',
            'LOG_HANDLER2_PERCENTAGE' => '50',
            'APP_ENV' => 'test',
        ]);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testInitializesFingersCrossedWrapperHandler(): void
    {
        $logger = $this->createLoggerFromEnv([
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'base_handler',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'LOG_HANDLER2_TYPE' => 'fingerscrossed',
            'LOG_HANDLER2_NAME' => 'fingerscrossed_handler',
            'LOG_HANDLER2_LEVEL' => 'INFO',
            'LOG_HANDLER2_WRAPS' => 'base_handler',
            'LOG_HANDLER2_ACTIVATION_LEVEL' => 'ERROR',
            'APP_ENV' => 'test',
        ]);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testInitializesConditionalWrapperHandler(): void
    {
        $logger = $this->createLoggerFromEnv([
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'base_handler',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'LOG_HANDLER2_TYPE' => 'conditional',
            'LOG_HANDLER2_NAME' => 'conditional_handler',
            'LOG_HANDLER2_LEVEL' => 'INFO',
            'LOG_HANDLER2_WRAPS' => 'base_handler',
            'LOG_HANDLER2_CONDITIONS' => 'AlwaysTrue',
            'APP_ENV' => 'test',
        ]);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testInitializesNestedWrapperHandlers(): void
    {
        $logger = $this->createLoggerFromEnv([
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'base_handler',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'LOG_HANDLER2_TYPE' => 'sampling',
            'LOG_HANDLER2_NAME' => 'sampling_handler',
            'LOG_HANDLER2_LEVEL' => 'INFO',
            'LOG_HANDLER2_WRAPS' => 'base_handler',
            'LOG_HANDLER2_STRATEGY' => 'percentage',
            'LOG_HANDLER2_PERCENTAGE' => '50',
            'LOG_HANDLER3_TYPE' => 'fingerscrossed',
            'LOG_HANDLER3_NAME' => 'fingerscrossed_handler',
            'LOG_HANDLER3_LEVEL' => 'INFO',
            'LOG_HANDLER3_WRAPS' => 'sampling_handler',
            'LOG_HANDLER3_ACTIVATION_LEVEL' => 'ERROR',
            'APP_ENV' => 'test',
        ]);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testInitializesConditionalWithComplexExpression(): void
    {
        $logger = $this->createLoggerFromEnv([
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'base_handler',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'LOG_HANDLER2_TYPE' => 'conditional',
            'LOG_HANDLER2_NAME' => 'conditional_handler',
            'LOG_HANDLER2_LEVEL' => 'INFO',
            'LOG_HANDLER2_WRAPS' => 'base_handler',
            'LOG_HANDLER2_CONDITIONS' => 'IsCli and AlwaysTrue or IsWeekend',
            'APP_ENV' => 'test',
        ]);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testThrowsExceptionWhenWrapperHandlerMissing(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('non_existent_handler');

        $this->createLoggerFromEnv([
            'LOG_HANDLER1_TYPE' => 'sampling',
            'LOG_HANDLER1_NAME' => 'sampling_handler',
            'LOG_HANDLER1_LEVEL' => 'INFO',
            'LOG_HANDLER1_WRAPS' => 'non_existent_handler',
            'LOG_HANDLER1_STRATEGY' => 'percentage',
            'LOG_HANDLER1_PERCENTAGE' => '50',
            'APP_ENV' => 'test',
        ]);
    }

    public function testThrowsExceptionWhenConditionClassNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('NonExistentCondition');

        $this->createLoggerFromEnv([
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'base_handler',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'LOG_HANDLER2_TYPE' => 'conditional',
            'LOG_HANDLER2_NAME' => 'conditional_handler',
            'LOG_HANDLER2_LEVEL' => 'INFO',
            'LOG_HANDLER2_WRAPS' => 'base_handler',
            'LOG_HANDLER2_CONDITIONS' => 'NonExistentCondition',
            'APP_ENV' => 'test',
        ]);
    }

    public function testInitializesMultipleIndependentWrappers(): void
    {
        $logger = $this->createLoggerFromEnv([
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'base1',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'LOG_HANDLER2_TYPE' => 'null',
            'LOG_HANDLER2_NAME' => 'base2',
            'LOG_HANDLER2_LEVEL' => 'DEBUG',
            'LOG_HANDLER3_TYPE' => 'sampling',
            'LOG_HANDLER3_NAME' => 'sampling1',
            'LOG_HANDLER3_LEVEL' => 'INFO',
            'LOG_HANDLER3_WRAPS' => 'base1',
            'LOG_HANDLER3_STRATEGY' => 'percentage',
            'LOG_HANDLER3_PERCENTAGE' => '50',
            'LOG_HANDLER4_TYPE' => 'fingerscrossed',
            'LOG_HANDLER4_NAME' => 'fingerscrossed1',
            'LOG_HANDLER4_LEVEL' => 'INFO',
            'LOG_HANDLER4_WRAPS' => 'base2',
            'LOG_HANDLER4_ACTIVATION_LEVEL' => 'ERROR',
            'APP_ENV' => 'test',
        ]);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }
}
