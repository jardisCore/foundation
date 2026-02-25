<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\LoggerConfigLoader;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for LoggerConfigLoader
 *
 * Tests loading of LOG_HANDLER{N}_* environment variables
 * and conversion to LoggerHandlerConfig objects.
 */
class LoggerConfigLoaderTest extends TestCase
{
    private LoggerConfigLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new LoggerConfigLoader();
    }

    public function testLoadsEmptyArrayWhenNoHandlersConfigured(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => null,
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(0, $configs);
    }

    public function testLoadsEmptyArrayWhenHandlerTypeIsEmpty(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => '',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(0, $configs);
    }

    public function testLoadsEmptyArrayWhenHandlerTypeIsWhitespace(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => '   ',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(0, $configs);
    }

    public function testLoadsSingleHandler(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_NAME' => 'app_log',
            'LOG_HANDLER1_LEVEL' => 'INFO',
            'LOG_HANDLER1_PATH' => '/var/log/app.log',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(1, $configs);
        $this->assertInstanceOf(LoggerHandlerConfig::class, $configs[0]);
        $this->assertSame('file', $configs[0]->type);
        $this->assertSame('app_log', $configs[0]->name);
        $this->assertSame('INFO', $configs[0]->level);
        $this->assertSame('/var/log/app.log', $configs[0]->getOption('path'));
    }

    public function testLoadsMultipleHandlers(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_NAME' => 'file_log',
            'LOG_HANDLER1_PATH' => '/var/log/app.log',
            'LOG_HANDLER2_TYPE' => 'slack',
            'LOG_HANDLER2_NAME' => 'slack_alerts',
            'LOG_HANDLER2_WEBHOOK' => 'https://hooks.slack.com/...',
            'LOG_HANDLER3_TYPE' => 'console',
            'LOG_HANDLER3_NAME' => 'console_log',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(3, $configs);
        $this->assertSame('file', $configs[0]->type);
        $this->assertSame('slack', $configs[1]->type);
        $this->assertSame('console', $configs[2]->type);
    }

    public function testStopsAtFirstMissingHandler(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_PATH' => '/var/log/app.log',
            'LOG_HANDLER2_TYPE' => null,
            'LOG_HANDLER3_TYPE' => 'slack',
            'LOG_HANDLER3_WEBHOOK' => 'https://...',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(1, $configs, 'Should stop at handler 2 (missing), ignoring handler 3');
    }

    public function testAppliesDefaultLevel(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_DEFAULT_LEVEL' => 'WARNING',
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_PATH' => '/var/log/app.log',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(1, $configs);
        $this->assertSame('WARNING', $configs[0]->level);
    }

    public function testAppliesDefaultFormat(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_DEFAULT_FORMAT' => 'text',
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_PATH' => '/var/log/app.log',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(1, $configs);
        $this->assertSame('text', $configs[0]->format);
    }

    public function testAppliesDefaultAlwaysTrue(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_DEFAULT_ALWAYS' => 'true',
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_PATH' => '/var/log/app.log',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(1, $configs);
        $this->assertTrue($configs[0]->always);
    }

    public function testAppliesDefaultAlwaysFalse(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_DEFAULT_ALWAYS' => 'false',
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_PATH' => '/var/log/app.log',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(1, $configs);
        $this->assertFalse($configs[0]->always);
    }

    public function testHandlerLevelOverridesDefault(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_DEFAULT_LEVEL' => 'WARNING',
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_LEVEL' => 'DEBUG',
            'LOG_HANDLER1_PATH' => '/var/log/app.log',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(1, $configs);
        $this->assertSame('DEBUG', $configs[0]->level);
    }

    public function testHandlerFormatOverridesDefault(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_DEFAULT_FORMAT' => 'json',
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_FORMAT' => 'text',
            'LOG_HANDLER1_PATH' => '/var/log/app.log',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(1, $configs);
        $this->assertSame('text', $configs[0]->format);
    }

    public function testLoadsHandlerSpecificOptions(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'slack',
            'LOG_HANDLER1_NAME' => 'alerts',
            'LOG_HANDLER1_WEBHOOK' => 'https://hooks.slack.com/services/xxx',
            'LOG_HANDLER1_CHANNEL' => '#alerts',
            'LOG_HANDLER1_USERNAME' => 'LogBot',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(1, $configs);
        $this->assertSame('https://hooks.slack.com/services/xxx', $configs[0]->getOption('webhook'));
        $this->assertSame('#alerts', $configs[0]->getOption('channel'));
        $this->assertSame('LogBot', $configs[0]->getOption('username'));
    }

    public function testDefaultsToJsonFormatWhenNotSet(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_DEFAULT_FORMAT' => null,
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_PATH' => '/var/log/app.log',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(1, $configs);
        $this->assertSame('json', $configs[0]->format);
    }

    public function testDefaultsToInfoLevelWhenNotSet(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_DEFAULT_LEVEL' => null,
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_PATH' => '/var/log/app.log',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(1, $configs);
        $this->assertSame('INFO', $configs[0]->level);
    }

    public function testDefaultAlwaysParsesVariousStringValues(): void
    {
        $testCases = [
            ['true', true],
            ['1', true],
            ['yes', true],
            ['on', true],
            ['TRUE', true],
            ['false', false],
            ['0', false],
            ['no', false],
            ['off', false],
        ];

        foreach ($testCases as [$input, $expected]) {
            $kernel = TestKernelFactory::create();
            TestKernelFactory::setEnv($kernel, [
                'LOG_DEFAULT_ALWAYS' => $input,
                'LOG_HANDLER1_TYPE' => 'null',
            ]);

            $configs = $this->loader->load($kernel);

            $this->assertSame(
                $expected,
                $configs[0]->always,
                "Expected LOG_DEFAULT_ALWAYS='$input' to parse as " . ($expected ? 'true' : 'false')
            );
        }
    }

    public function testConvertsParameterNamesToLowercase(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'LOG_HANDLER1_TYPE' => 'file',
            'LOG_HANDLER1_PATH' => '/var/log/app.log',
            'LOG_HANDLER1_MAX_FILES' => '10',
        ]);

        $configs = $this->loader->load($kernel);

        $this->assertCount(1, $configs);
        $this->assertSame('10', $configs[0]->getOption('max_files'));
    }
}
