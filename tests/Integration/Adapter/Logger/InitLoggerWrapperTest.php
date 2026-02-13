<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger;

use Exception;
use JardisCore\Foundation\Adapter\Logger\InitLogger;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Integration Tests for InitLogger with Wrapper Handlers
 *
 * Tests wrapper handler integration with real configuration
 */
class InitLoggerWrapperTest extends TestCase
{
    public function testInitializesSamplingWrapperHandler(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'base_handler',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'LOG_HANDLER2_TYPE' => 'sampling',
            'LOG_HANDLER2_NAME' => 'sampling_handler',
            'LOG_HANDLER2_LEVEL' => 'INFO',
            'LOG_HANDLER2_WRAPS' => 'base_handler',
            'LOG_HANDLER2_STRATEGY' => 'percentage',
            'LOG_HANDLER2_PERCENTAGE' => '50',
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testInitializesFingersCrossedWrapperHandler(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'base_handler',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'LOG_HANDLER2_TYPE' => 'fingerscrossed',
            'LOG_HANDLER2_NAME' => 'fingerscrossed_handler',
            'LOG_HANDLER2_LEVEL' => 'INFO',
            'LOG_HANDLER2_WRAPS' => 'base_handler',
            'LOG_HANDLER2_ACTIVATION_LEVEL' => 'ERROR',
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testInitializesConditionalWrapperHandler(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'base_handler',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'LOG_HANDLER2_TYPE' => 'conditional',
            'LOG_HANDLER2_NAME' => 'conditional_handler',
            'LOG_HANDLER2_LEVEL' => 'INFO',
            'LOG_HANDLER2_WRAPS' => 'base_handler',
            'LOG_HANDLER2_CONDITIONS' => 'AlwaysTrue',
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testInitializesNestedWrapperHandlers(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
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
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testInitializesConditionalWithComplexExpression(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'base_handler',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'LOG_HANDLER2_TYPE' => 'conditional',
            'LOG_HANDLER2_NAME' => 'conditional_handler',
            'LOG_HANDLER2_LEVEL' => 'INFO',
            'LOG_HANDLER2_WRAPS' => 'base_handler',
            'LOG_HANDLER2_CONDITIONS' => 'IsCli and AlwaysTrue or IsWeekend',
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testThrowsExceptionWhenWrapperHandlerMissing(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'sampling',
            'LOG_HANDLER1_NAME' => 'sampling_handler',
            'LOG_HANDLER1_LEVEL' => 'INFO',
            'LOG_HANDLER1_WRAPS' => 'non_existent_handler',
            'LOG_HANDLER1_STRATEGY' => 'percentage',
            'LOG_HANDLER1_PERCENTAGE' => '50',
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('non_existent_handler');

        $initLogger->__invoke($kernel);
    }

    public function testThrowsExceptionWhenConditionClassNotFound(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'base_handler',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'LOG_HANDLER2_TYPE' => 'conditional',
            'LOG_HANDLER2_NAME' => 'conditional_handler',
            'LOG_HANDLER2_LEVEL' => 'INFO',
            'LOG_HANDLER2_WRAPS' => 'base_handler',
            'LOG_HANDLER2_CONDITIONS' => 'NonExistentCondition',
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('NonExistentCondition');

        $initLogger->__invoke($kernel);
    }

    public function testInitializesMultipleIndependentWrappers(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
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
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }
}
