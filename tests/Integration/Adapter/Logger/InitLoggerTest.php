<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger;

use JardisCore\Foundation\Adapter\Logger\InitLogger;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Integration Tests for InitLogger
 *
 * Tests logger initialization with real configuration
 */
class InitLoggerTest extends TestCase
{
    public function testInitializesLoggerWithFileHandler(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_NAME' => 'app_log',
            'LOG_HANDLER1_LEVEL' => 'INFO',
            'LOG_HANDLER1_PATH' => sys_get_temp_dir() . '/test.log',
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testReturnsNullWhenNoHandlerEnabled(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            // Explicitly unset any LOG_HANDLER*_TYPE from .env
            'LOG_HANDLER1_TYPE' => null,
            'LOG_HANDLER2_TYPE' => null,
            'LOG_HANDLER3_TYPE' => null,
            'LOG_HANDLER4_TYPE' => null,
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNull($logger, 'Logger should be null when no handler is enabled');
    }

    public function testInitializesWithConsoleHandler(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'console',
            'LOG_HANDLER1_NAME' => 'console_log',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testInitializesWithErrorLogHandler(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'errorlog',
            'LOG_HANDLER1_NAME' => 'error_log',
            'LOG_HANDLER1_LEVEL' => 'ERROR',
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testInitializesWithSyslogHandler(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'syslog',
            'LOG_HANDLER1_NAME' => 'syslog',
            'LOG_HANDLER1_LEVEL' => 'INFO',
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testInitializesWithNullHandler(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'null_log',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testInitializesWithMultipleHandlers(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_NAME' => 'app_log',
            'LOG_HANDLER1_LEVEL' => 'INFO',
            'LOG_HANDLER1_PATH' => sys_get_temp_dir() . '/test.log',
            'LOG_HANDLER2_TYPE' => 'console',
            'LOG_HANDLER2_NAME' => 'console_log',
            'LOG_HANDLER2_LEVEL' => 'DEBUG',
            'LOG_HANDLER3_TYPE' => 'null',
            'LOG_HANDLER3_NAME' => 'null_log',
            'LOG_HANDLER3_LEVEL' => 'DEBUG',
            'APP_ENV' => 'test'
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testUsesAppEnvAsContext(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'null_log',
            'LOG_HANDLER1_LEVEL' => 'INFO',
            'APP_ENV' => 'production'
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
    }

    public function testDefaultsToAppWhenAppEnvNotSet(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'null',
            'LOG_HANDLER1_NAME' => 'null_log',
            'LOG_HANDLER1_LEVEL' => 'INFO',
            'APP_ENV' => null
        ]);

        $initLogger = new InitLogger();
        $logger = $initLogger->__invoke($kernel);

        $this->assertNotNull($logger);
    }
}
