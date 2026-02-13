<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Handler;

use JardisCore\Foundation\Adapter\Logger\Handler\FileLogHandler;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use PHPUnit\Framework\TestCase;

class FileLogHandlerTest extends TestCase
{
    public function testCreatesFileLogHandler(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'test_file',
            'level' => 'INFO',
            'path' => sys_get_temp_dir() . '/test.log',
        ]);

        $handler = new FileLogHandler();
        $logCommand = $handler->__invoke($config);

        $this->assertInstanceOf(LogCommandInterface::class, $logCommand);
    }

    public function testCreatesFileLogHandlerWithRotation(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'test_file_rotation',
            'level' => 'DEBUG',
            'path' => sys_get_temp_dir() . '/test_rotate.log',
            'max_files' => 7,
        ]);

        $handler = new FileLogHandler();
        $logCommand = $handler->__invoke($config);

        $this->assertInstanceOf(LogCommandInterface::class, $logCommand);
    }

    public function testThrowsExceptionForMissingPath(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'test_file',
            'level' => 'INFO'
            // Missing 'path'
        ]);

        $handler = new FileLogHandler();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('path');

        $handler->__invoke($config);
    }

    public function testThrowsExceptionForEmptyPath(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'test_file',
            'level' => 'INFO',
            'path' => '   ' // Empty after trim
        ]);

        $handler = new FileLogHandler();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('path');

        $handler->__invoke($config);
    }

    public function testCreatesFileLogHandlerWithLineFormat(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'test_file',
            'level' => 'INFO',
            'path' => sys_get_temp_dir() . '/test.log',
            'format' => 'line'
        ]);

        $handler = new FileLogHandler();
        $logCommand = $handler->__invoke($config);

        $this->assertInstanceOf(LogCommandInterface::class, $logCommand);
    }

    public function testCreatesFileLogHandlerWithTextFormat(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'test_file',
            'level' => 'INFO',
            'path' => sys_get_temp_dir() . '/test.log',
            'format' => 'text'
        ]);

        $handler = new FileLogHandler();
        $logCommand = $handler->__invoke($config);

        $this->assertInstanceOf(LogCommandInterface::class, $logCommand);
    }

    public function testCreatesFileLogHandlerWithHumanFormat(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'test_file',
            'level' => 'INFO',
            'path' => sys_get_temp_dir() . '/test.log',
            'format' => 'human'
        ]);

        $handler = new FileLogHandler();
        $logCommand = $handler->__invoke($config);

        $this->assertInstanceOf(LogCommandInterface::class, $logCommand);
    }

    public function testCreatesFileLogHandlerWithJsonFormat(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'test_file',
            'level' => 'INFO',
            'path' => sys_get_temp_dir() . '/test.log',
            'format' => 'json'
        ]);

        $handler = new FileLogHandler();
        $logCommand = $handler->__invoke($config);

        $this->assertInstanceOf(LogCommandInterface::class, $logCommand);
    }

    public function testCreatesFileLogHandlerWithDefaultJsonFormatForUnknown(): void
    {
        $config = LoggerHandlerConfig::fromArray([
            'type' => 'file',
            'name' => 'test_file',
            'level' => 'INFO',
            'path' => sys_get_temp_dir() . '/test.log',
            'format' => 'unknown_format' // Should default to JSON
        ]);

        $handler = new FileLogHandler();
        $logCommand = $handler->__invoke($config);

        $this->assertInstanceOf(LogCommandInterface::class, $logCommand);
    }
}
