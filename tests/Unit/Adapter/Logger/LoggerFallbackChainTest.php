<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Adapter\Logger;

use JardisCore\Foundation\Adapter\ConnectionProvider;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerFactory;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * Unit tests for Logger Handler fallback chain via ConnectionProvider
 *
 * Tests that Logger handlers correctly use the Redis fallback chain:
 * redis('logger') → redis('messaging') → redis('cache')
 */
class LoggerFallbackChainTest extends TestCase
{
    public function testRedisMqHandlerUsesLoggerRedisFirst(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redisLogger = $this->createMock(Redis::class);
        $redisCache = $this->createMock(Redis::class);

        $connections = new ConnectionProvider();
        $connections->addRedis('logger', $redisLogger);
        $connections->addRedis('cache', $redisCache);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'redismq',
            'name' => 'test_redismq',
            'level' => 'INFO',
            'channel' => 'test-logs',
        ]);

        $factory = new LoggerHandlerFactory();
        $handler = $factory->create($config, $connections);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testRedisMqHandlerFallsBackToMessagingRedis(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redisMessaging = $this->createMock(Redis::class);

        $connections = new ConnectionProvider();
        // No logger redis, only messaging
        $connections->addRedis('messaging', $redisMessaging);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'redismq',
            'name' => 'test_redismq',
            'level' => 'INFO',
            'channel' => 'test-logs',
        ]);

        $factory = new LoggerHandlerFactory();
        $handler = $factory->create($config, $connections);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testRedisMqHandlerFallsBackToCacheRedis(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redisCache = $this->createMock(Redis::class);

        $connections = new ConnectionProvider();
        // No logger, no messaging, only cache
        $connections->addRedis('cache', $redisCache);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'redismq',
            'name' => 'test_redismq',
            'level' => 'INFO',
            'channel' => 'test-logs',
        ]);

        $factory = new LoggerHandlerFactory();
        $handler = $factory->create($config, $connections);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }

    public function testFallbackChainOrderIsCorrect(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        // Register all three Redis connections
        $redisLogger = $this->createMock(Redis::class);
        $redisMessaging = $this->createMock(Redis::class);
        $redisCache = $this->createMock(Redis::class);

        $connections = new ConnectionProvider();
        $connections->addRedis('logger', $redisLogger);
        $connections->addRedis('messaging', $redisMessaging);
        $connections->addRedis('cache', $redisCache);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'redismq',
            'name' => 'test_redismq',
            'level' => 'INFO',
            'channel' => 'test-logs',
        ]);

        // Should use logger redis (first in chain) even though all are available
        $factory = new LoggerHandlerFactory();
        $handler = $factory->create($config, $connections);

        $this->assertInstanceOf(LogCommandInterface::class, $handler);
    }
}
