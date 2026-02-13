<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger;

use JardisCore\Foundation\Adapter\Logger\LoggerConfigMerger;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LoggerConfigMerger
 *
 * Tests deduplication and merging logic
 */
class LoggerConfigMergerTest extends TestCase
{
    private LoggerConfigMerger $merger;

    protected function setUp(): void
    {
        $this->merger = new LoggerConfigMerger();
    }

    public function testKeepsUniqueConfigurations(): void
    {
        $configs = [
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'INFO',
                'path' => '/var/log/app.log',
            ]),
            LoggerHandlerConfig::fromArray([
                'type' => 'slack',
                'name' => 'alerts',
                'level' => 'ERROR',
                'webhook' => 'https://hooks.slack.com/...',
            ]),
        ];

        $merged = $this->merger->merge($configs);

        $this->assertCount(2, $merged);
    }

    public function testRemovesDuplicateHandlers(): void
    {
        $configs = [
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'INFO',
                'path' => '/var/log/app.log',
            ]),
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'INFO',
                'path' => '/var/log/app.log',
            ]),
        ];

        $merged = $this->merger->merge($configs);

        $this->assertCount(1, $merged);
    }

    public function testUsesMoreVerboseLogLevel(): void
    {
        $configs = [
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'ERROR',
                'path' => '/var/log/app.log',
            ]),
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'DEBUG',
                'path' => '/var/log/app.log',
            ]),
        ];

        $merged = $this->merger->merge($configs);

        $this->assertCount(1, $merged);
        $this->assertEquals('DEBUG', $merged[0]->level);
    }

    public function testKeepsHandlersWithAlwaysTrue(): void
    {
        $configs = [
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'INFO',
                'path' => '/var/log/app.log',
                'always' => 'true',
            ]),
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'INFO',
                'path' => '/var/log/app.log',
                'always' => 'true',
            ]),
        ];

        $merged = $this->merger->merge($configs);

        $this->assertCount(2, $merged, 'Handlers with always=true should not be deduplicated');
    }

    public function testBCIsKingPrinciple(): void
    {
        $configs = [
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'INFO',
                'path' => '/var/log/shared.log',
            ]),
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'INFO',
                'path' => '/var/log/domain.log',
            ]),
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'INFO',
                'path' => '/var/log/bc.log',
            ]),
        ];

        $merged = $this->merger->merge($configs);

        $this->assertCount(1, $merged);
        $this->assertEquals('/var/log/bc.log', $merged[0]->options['path'], 'Should use BC config (last one)');
    }

    public function testKeepsHandlersWithDifferentNames(): void
    {
        $configs = [
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'INFO',
                'path' => '/var/log/app.log',
            ]),
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'error_log',
                'level' => 'INFO',
                'path' => '/var/log/error.log',
            ]),
        ];

        $merged = $this->merger->merge($configs);

        $this->assertCount(2, $merged);
    }

    public function testKeepsHandlersWithDifferentFormats(): void
    {
        $configs = [
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'INFO',
                'format' => 'json',
                'path' => '/var/log/app.log',
            ]),
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'INFO',
                'format' => 'text',
                'path' => '/var/log/app.log',
            ]),
        ];

        $merged = $this->merger->merge($configs);

        $this->assertCount(2, $merged, 'Different formats should result in different handlers');
    }

    public function testHandlesEmptyConfigArray(): void
    {
        $merged = $this->merger->merge([]);

        $this->assertCount(0, $merged);
    }

    public function testHandlesSingleConfig(): void
    {
        $configs = [
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'app_log',
                'level' => 'INFO',
                'path' => '/var/log/app.log',
            ]),
        ];

        $merged = $this->merger->merge($configs);

        $this->assertCount(1, $merged);
    }

    public function testKeepsWrapperHandlers(): void
    {
        $configs = [
            LoggerHandlerConfig::fromArray([
                'type' => 'null',
                'name' => 'base',
                'level' => 'DEBUG',
            ]),
            LoggerHandlerConfig::fromArray([
                'type' => 'sampling',
                'name' => 'sampled',
                'level' => 'INFO',
                'wraps' => 'base',
                'strategy' => 'percentage',
                'percentage' => '50',
            ]),
        ];

        $merged = $this->merger->merge($configs);

        $this->assertCount(2, $merged);
    }

    public function testDifferentHandlerTypes(): void
    {
        $configs = [
            LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'name' => 'log',
                'level' => 'INFO',
                'path' => '/var/log/app.log',
            ]),
            LoggerHandlerConfig::fromArray([
                'type' => 'slack',
                'name' => 'log',
                'level' => 'INFO',
                'webhook' => 'https://...',
            ]),
        ];

        $merged = $this->merger->merge($configs);

        $this->assertCount(2, $merged, 'Different types should not be merged');
    }

    public function testLogLevelHierarchy(): void
    {
        $levels = [
            ['ERROR', 'DEBUG', 'DEBUG'],  // DEBUG is more verbose
            ['WARNING', 'ERROR', 'WARNING'],  // WARNING is more verbose
            ['INFO', 'WARNING', 'INFO'],  // INFO is more verbose
            ['EMERGENCY', 'DEBUG', 'DEBUG'],  // DEBUG is most verbose
        ];

        foreach ($levels as [$level1, $level2, $expected]) {
            $configs = [
                LoggerHandlerConfig::fromArray([
                    'type' => 'file',
                    'name' => 'test',
                    'level' => $level1,
                    'path' => '/var/log/test.log',
                ]),
                LoggerHandlerConfig::fromArray([
                    'type' => 'file',
                    'name' => 'test',
                    'level' => $level2,
                    'path' => '/var/log/test.log',
                ]),
            ];

            $merged = $this->merger->merge($configs);

            $this->assertCount(1, $merged);
            $this->assertEquals(
                $expected,
                $merged[0]->level,
                "Expected $expected when merging $level1 and $level2"
            );
        }
    }
}
