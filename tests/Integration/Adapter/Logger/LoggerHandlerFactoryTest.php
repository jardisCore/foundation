<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerFactory;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LoggerHandlerFactory
 *
 * Tests handler creation logic including wrapper handlers
 */
class LoggerHandlerFactoryTest extends TestCase
{
    private LoggerHandlerFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new LoggerHandlerFactory();
    }

    public function testCreatesFileHandler(): void
    {
        $kernel = TestKernelFactory::create();
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'test_file',
            'level' => 'INFO',
            'path' => sys_get_temp_dir() . '/test.log',
        ]);

        $handler = $this->factory->create($config, $kernel);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testCreatesConsoleHandler(): void
    {
        $kernel = TestKernelFactory::create();
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'console',
            'name' => 'test_console',
            'level' => 'DEBUG',
        ]);

        $handler = $this->factory->create($config, $kernel);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testCreatesErrorLogHandler(): void
    {
        $kernel = TestKernelFactory::create();
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'errorlog',
            'name' => 'test_errorlog',
            'level' => 'ERROR',
        ]);

        $handler = $this->factory->create($config, $kernel);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testCreatesSyslogHandler(): void
    {
        $kernel = TestKernelFactory::create();
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'syslog',
            'name' => 'test_syslog',
            'level' => 'INFO',
        ]);

        $handler = $this->factory->create($config, $kernel);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testCreatesNullHandler(): void
    {
        $kernel = TestKernelFactory::create();
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'null',
            'name' => 'test_null',
            'level' => 'DEBUG',
        ]);

        $handler = $this->factory->create($config, $kernel);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testThrowsExceptionForUnknownHandlerType(): void
    {
        $kernel = TestKernelFactory::create();
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'unknown_type',
            'name' => 'test',
            'level' => 'INFO',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported log handler type');

        $this->factory->create($config, $kernel);
    }

    public function testCreatesSamplingWrapperHandler(): void
    {
        $kernel = TestKernelFactory::create();

        $wrappedConfig = LoggerHandlerConfig::fromArray([
            'type' => 'null',
            'name' => 'wrapped_handler',
            'level' => 'DEBUG',
        ]);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'sampling_handler',
            'level' => 'INFO',
            'wraps' => 'wrapped_handler',
            'strategy' => 'percentage',
            'percentage' => '50',
        ]);

        $handler = $this->factory->create($config, $kernel, [$wrappedConfig, $config]);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testCreatesFingersCrossedWrapperHandler(): void
    {
        $kernel = TestKernelFactory::create();

        $wrappedConfig = LoggerHandlerConfig::fromArray([
            'type' => 'null',
            'name' => 'wrapped_handler',
            'level' => 'DEBUG',
        ]);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'fingerscrossed',
            'name' => 'fingerscrossed_handler',
            'level' => 'INFO',
            'wraps' => 'wrapped_handler',
            'activation_level' => 'ERROR',
        ]);

        $handler = $this->factory->create($config, $kernel, [$wrappedConfig, $config]);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testCreatesConditionalWrapperHandler(): void
    {
        $kernel = TestKernelFactory::create();

        $wrappedConfig = LoggerHandlerConfig::fromArray([
            'type' => 'null',
            'name' => 'wrapped_handler',
            'level' => 'DEBUG',
        ]);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'conditional',
            'name' => 'conditional_handler',
            'level' => 'INFO',
            'wraps' => 'wrapped_handler',
            'conditions' => 'AlwaysTrue',
        ]);

        $handler = $this->factory->create($config, $kernel, [$wrappedConfig, $config]);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testThrowsExceptionWhenWrappedHandlerNotFound(): void
    {
        $kernel = TestKernelFactory::create();

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'sampling_handler',
            'level' => 'INFO',
            'wraps' => 'non_existent_handler',
            'strategy' => 'percentage',
            'percentage' => '50',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("references unknown handler 'non_existent_handler'");

        $this->factory->create($config, $kernel, [$config]);
    }

    public function testThrowsExceptionWhenWrapperHasNoWraps(): void
    {
        $kernel = TestKernelFactory::create();

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'sampling_handler',
            'level' => 'INFO',
            // Missing 'wraps' parameter
            'strategy' => 'percentage',
            'percentage' => '50',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("requires 'wraps' parameter");

        $this->factory->create($config, $kernel, [$config]);
    }

    public function testSupportsNestedWrapping(): void
    {
        $kernel = TestKernelFactory::create();

        $baseConfig = LoggerHandlerConfig::fromArray([
            'type' => 'null',
            'name' => 'base_handler',
            'level' => 'DEBUG',
        ]);

        $samplingConfig = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'sampling_handler',
            'level' => 'INFO',
            'wraps' => 'base_handler',
            'strategy' => 'percentage',
            'percentage' => '50',
        ]);

        $fingersConfig = LoggerHandlerConfig::fromArray([
            'type' => 'fingerscrossed',
            'name' => 'fingerscrossed_handler',
            'level' => 'INFO',
            'wraps' => 'sampling_handler',
            'activation_level' => 'ERROR',
        ]);

        $handler = $this->factory->create(
            $fingersConfig,
            $kernel,
            [$baseConfig, $samplingConfig, $fingersConfig]
        );

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testCreatesSlackHandler(): void
    {
        $kernel = TestKernelFactory::create();
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'slack',
            'name' => 'test_slack',
            'level' => 'ERROR',
            'webhook' => 'https://hooks.slack.com/services/TEST',
        ]);

        $handler = $this->factory->create($config, $kernel);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testCreatesTeamsHandler(): void
    {
        $kernel = TestKernelFactory::create();
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'teams',
            'name' => 'test_teams',
            'level' => 'ERROR',
            'webhook' => 'https://outlook.office.com/webhook/TEST',
        ]);

        $handler = $this->factory->create($config, $kernel);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testCreatesLokiHandler(): void
    {
        $kernel = TestKernelFactory::create();
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'loki',
            'name' => 'test_loki',
            'level' => 'INFO',
            'url' => 'http://localhost:3100',
        ]);

        $handler = $this->factory->create($config, $kernel);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testCreatesWebhookHandler(): void
    {
        $kernel = TestKernelFactory::create();
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'webhook',
            'name' => 'test_webhook',
            'level' => 'WARNING',
            'url' => 'https://example.com/webhook',
        ]);

        $handler = $this->factory->create($config, $kernel);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testCreatesEmailHandler(): void
    {
        $kernel = TestKernelFactory::create();
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'email',
            'name' => 'test_email',
            'level' => 'CRITICAL',
            'to' => 'admin@example.com',
            'from' => 'logger@example.com',
        ]);

        $handler = $this->factory->create($config, $kernel);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testCreatesStashHandler(): void
    {
        $kernel = TestKernelFactory::create();
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'stash',
            'name' => 'test_stash',
            'level' => 'INFO',
            'host' => 'localhost',
            'port' => 5000,
        ]);

        $handler = $this->factory->create($config, $kernel);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testCreatesBrowserConsoleHandler(): void
    {
        $kernel = TestKernelFactory::create();
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'browserconsole',
            'name' => 'test_browser',
            'level' => 'DEBUG',
        ]);

        $handler = $this->factory->create($config, $kernel);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testThrowsExceptionWhenWrapperHasEmptyWraps(): void
    {
        $kernel = TestKernelFactory::create();

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'sampling',
            'name' => 'sampling_handler',
            'level' => 'INFO',
            'wraps' => '  ',  // Empty string after trim
            'strategy' => 'percentage',
            'percentage' => '50',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("requires 'wraps' parameter");

        $this->factory->create($config, $kernel, [$config]);
    }

    public function testConditionalHandlerSupportsMultipleWrappedHandlers(): void
    {
        $kernel = TestKernelFactory::create();

        $wrapped1 = LoggerHandlerConfig::fromArray([
            'type' => 'null',
            'name' => 'handler1',
            'level' => 'DEBUG',
        ]);

        $wrapped2 = LoggerHandlerConfig::fromArray([
            'type' => 'console',
            'name' => 'handler2',
            'level' => 'INFO',
        ]);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'conditional',
            'name' => 'conditional_handler',
            'level' => 'INFO',
            'wraps' => 'handler1,handler2',  // Multiple handlers
            'conditions' => 'AlwaysTrue',
        ]);

        $handler = $this->factory->create($config, $kernel, [$wrapped1, $wrapped2, $config]);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }
}
