<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Adapter\Logger;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LoggerHandlerConfig
 *
 * Tests the configuration DTO for log handlers.
 */
class LoggerHandlerConfigTest extends TestCase
{
    public function testFromArrayCreatesConfigWithRequiredType(): void
    {
        $config = LoggerHandlerConfig::fromArray(['type' => 'file']);

        $this->assertSame('file', $config->type);
    }

    public function testFromArrayThrowsExceptionWhenTypeMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires "type" parameter');

        LoggerHandlerConfig::fromArray([]);
    }

    public function testFromArrayThrowsExceptionWhenTypeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires "type" parameter');

        LoggerHandlerConfig::fromArray(['type' => '']);
    }

    public function testFromArrayThrowsExceptionWhenTypeWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires "type" parameter');

        LoggerHandlerConfig::fromArray(['type' => '   ']);
    }

    public function testFromArrayThrowsExceptionWhenTypeNotString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires "type" parameter');

        LoggerHandlerConfig::fromArray(['type' => 123]);
    }

    public function testFromArraySetsName(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'app_log',
        ]);

        $this->assertSame('app_log', $config->name);
    }

    public function testFromArraySetsNameToNullWhenEmpty(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => '',
        ]);

        $this->assertNull($config->name);
    }

    public function testFromArraySetsFormat(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'format' => 'json',
        ]);

        $this->assertSame('json', $config->format);
    }

    public function testFromArraySetsLevel(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'level' => 'WARNING',
        ]);

        $this->assertSame('WARNING', $config->level);
    }

    public function testFromArrayNormalizesLevelToUppercase(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'level' => 'debug',
        ]);

        $this->assertSame('DEBUG', $config->level);
    }

    public function testFromArrayDefaultsLevelToInfo(): void
    {
        $config = LoggerHandlerConfig::fromArray(['type' => 'file']);

        $this->assertSame('INFO', $config->level);
    }

    public function testFromArraySetsAlwaysTrue(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'always' => true,
        ]);

        $this->assertTrue($config->always);
    }

    public function testFromArraySetsAlwaysFalse(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'always' => false,
        ]);

        $this->assertFalse($config->always);
    }

    public function testFromArrayParsesAlwaysStringTrue(): void
    {
        $testCases = ['true', '1', 'yes', 'on'];

        foreach ($testCases as $value) {
            $config = LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'always' => $value,
            ]);

            $this->assertTrue($config->always, "Expected '$value' to parse as true");
        }
    }

    public function testFromArrayParsesAlwaysStringFalse(): void
    {
        $testCases = ['false', '0', 'no', 'off', 'anything'];

        foreach ($testCases as $value) {
            $config = LoggerHandlerConfig::fromArray([
                'type' => 'file',
                'always' => $value,
            ]);

            $this->assertFalse($config->always, "Expected '$value' to parse as false");
        }
    }

    public function testFromArrayDefaultsAlwaysToFalse(): void
    {
        $config = LoggerHandlerConfig::fromArray(['type' => 'file']);

        $this->assertFalse($config->always);
    }

    public function testFromArrayPutsExtraParametersInOptions(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'path' => '/var/log/app.log',
            'max_files' => 10,
            'permission' => 0644,
        ]);

        $this->assertSame('/var/log/app.log', $config->options['path']);
        $this->assertSame(10, $config->options['max_files']);
        $this->assertSame(0644, $config->options['permission']);
    }

    public function testFromArrayExcludesCoreParametersFromOptions(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'app_log',
            'format' => 'json',
            'level' => 'INFO',
            'always' => true,
            'path' => '/var/log/app.log',
        ]);

        $this->assertArrayNotHasKey('type', $config->options);
        $this->assertArrayNotHasKey('name', $config->options);
        $this->assertArrayNotHasKey('format', $config->options);
        $this->assertArrayNotHasKey('level', $config->options);
        $this->assertArrayNotHasKey('always', $config->options);
        $this->assertArrayHasKey('path', $config->options);
    }

    public function testFromArrayAppliesDefaults(): void
    {
        $defaults = [
            'format' => 'text',
            'level' => 'WARNING',
            'always' => true,
        ];

        $config = LoggerHandlerConfig::fromArray(['type' => 'file'], $defaults);

        $this->assertSame('text', $config->format);
        $this->assertSame('WARNING', $config->level);
        $this->assertTrue($config->always);
    }

    public function testFromArrayConfigOverridesDefaults(): void
    {
        $defaults = [
            'format' => 'text',
            'level' => 'WARNING',
        ];

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'format' => 'json',
            'level' => 'DEBUG',
        ], $defaults);

        $this->assertSame('json', $config->format);
        $this->assertSame('DEBUG', $config->level);
    }

    public function testGetOptionReturnsValue(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'slack',
            'webhook' => 'https://hooks.slack.com/...',
        ]);

        $this->assertSame('https://hooks.slack.com/...', $config->getOption('webhook'));
    }

    public function testGetOptionReturnsDefaultWhenNotFound(): void
    {
        $config = LoggerHandlerConfig::fromArray(['type' => 'file']);

        $this->assertNull($config->getOption('nonexistent'));
        $this->assertSame('default', $config->getOption('nonexistent', 'default'));
    }

    public function testIsAlwaysReturnsAlwaysValue(): void
    {
        $configTrue = LoggerHandlerConfig::fromArray(['type' => 'file', 'always' => true]);
        $configFalse = LoggerHandlerConfig::fromArray(['type' => 'file', 'always' => false]);

        $this->assertTrue($configTrue->isAlways());
        $this->assertFalse($configFalse->isAlways());
    }

    public function testToArrayReturnsAllProperties(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'app_log',
            'format' => 'json',
            'level' => 'INFO',
            'always' => true,
            'path' => '/var/log/app.log',
        ]);

        $array = $config->toArray();

        $this->assertSame('file', $array['type']);
        $this->assertSame('app_log', $array['name']);
        $this->assertSame('json', $array['format']);
        $this->assertSame('INFO', $array['level']);
        $this->assertTrue($array['always']);
        $this->assertSame(['path' => '/var/log/app.log'], $array['options']);
    }

    public function testGetUniqueKeyGeneratesKeyFromDefaultFields(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'app_log',
            'level' => 'INFO',
        ]);

        $key = $config->getUniqueKey();

        $this->assertSame('file|app_log|INFO', $key);
    }

    public function testGetUniqueKeyUsesCustomKeysForHandlerType(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'app_log',
            'format' => 'json',
            'level' => 'INFO',
        ]);

        $deduplicationKeys = [
            'file' => ['type', 'name', 'format', 'level'],
        ];

        $key = $config->getUniqueKey($deduplicationKeys);

        $this->assertSame('file|app_log|json|INFO', $key);
    }

    public function testGetUniqueKeyHandlesNullName(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'console',
            'level' => 'DEBUG',
        ]);

        $key = $config->getUniqueKey();

        $this->assertSame('console||DEBUG', $key);
    }

    public function testConstructorSetsAllProperties(): void
    {
        $config = new LoggerHandlerConfig(
            type: 'slack',
            name: 'alerts',
            format: 'json',
            level: 'ERROR',
            always: true,
            options: ['webhook' => 'https://...'],
        );

        $this->assertSame('slack', $config->type);
        $this->assertSame('alerts', $config->name);
        $this->assertSame('json', $config->format);
        $this->assertSame('ERROR', $config->level);
        $this->assertTrue($config->always);
        $this->assertSame(['webhook' => 'https://...'], $config->options);
    }

    public function testTrimsTypeValue(): void
    {
        $config = LoggerHandlerConfig::fromArray(['type' => '  file  ']);

        $this->assertSame('file', $config->type);
    }

    public function testTrimsNameValue(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => '  app_log  ',
        ]);

        $this->assertSame('app_log', $config->name);
    }
}
