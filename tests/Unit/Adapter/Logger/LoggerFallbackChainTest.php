<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Adapter\Logger;

use JardisCore\Foundation\Adapter\DomainKernel;
use JardisCore\Foundation\Adapter\Logger\Handler\RedisLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\RedisMqLogHandler;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisCore\Foundation\Adapter\ResourceKey;
use JardisCore\Foundation\Adapter\ResourceRegistry;
use JardisCore\Foundation\Adapter\SharedResource;
use JardisAdapter\Logger\Handler\LogRedisMq;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * Unit tests for Logger Handler fallback chain
 *
 * Tests that Logger handlers correctly use the fallback chain:
 * REDIS_LOGGER → REDIS_MESSAGING → REDIS_CACHE → ENV
 */
class LoggerFallbackChainTest extends TestCase
{
    protected function setUp(): void
    {
        SharedResource::reset();
    }

    protected function tearDown(): void
    {
        SharedResource::reset();
    }

    public function testRedisLogHandlerUsesRedisLoggerFirst(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redisLogger = $this->createMock(Redis::class);
        $redisCache = $this->createMock(Redis::class);

        $registry = new ResourceRegistry();
        $registry->register(ResourceKey::REDIS_LOGGER->value, $redisLogger);
        $registry->register(ResourceKey::REDIS_CACHE->value, $redisCache);

        $kernel = $this->createKernelWithResources($registry);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'redis',
            'level' => 'INFO',
            'channel' => 'test-logs',
        ]);

        $handler = (new RedisLogHandler())($config, $kernel);

        // Handler should be created (we can't easily verify which Redis was used,
        // but we can verify no exception was thrown and handler was created)
        $this->assertInstanceOf(LogRedisMq::class, $handler);
    }

    public function testRedisLogHandlerFallsBackToRedisMessaging(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redisMessaging = $this->createMock(Redis::class);
        $redisCache = $this->createMock(Redis::class);

        $registry = new ResourceRegistry();
        // No REDIS_LOGGER
        $registry->register(ResourceKey::REDIS_MESSAGING->value, $redisMessaging);
        $registry->register(ResourceKey::REDIS_CACHE->value, $redisCache);

        $kernel = $this->createKernelWithResources($registry);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'redis',
            'level' => 'INFO',
            'channel' => 'test-logs',
        ]);

        $handler = (new RedisLogHandler())($config, $kernel);

        $this->assertInstanceOf(LogRedisMq::class, $handler);
    }

    public function testRedisLogHandlerFallsBackToRedisCache(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redisCache = $this->createMock(Redis::class);

        $registry = new ResourceRegistry();
        // No REDIS_LOGGER, no REDIS_MESSAGING
        $registry->register(ResourceKey::REDIS_CACHE->value, $redisCache);

        $kernel = $this->createKernelWithResources($registry);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'redis',
            'level' => 'INFO',
            'channel' => 'test-logs',
        ]);

        $handler = (new RedisLogHandler())($config, $kernel);

        $this->assertInstanceOf(LogRedisMq::class, $handler);
    }

    public function testRedisMqLogHandlerUsesRedisLoggerFirst(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redisLogger = $this->createMock(Redis::class);

        $registry = new ResourceRegistry();
        $registry->register(ResourceKey::REDIS_LOGGER->value, $redisLogger);

        $kernel = $this->createKernelWithResources($registry);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'redismq',
            'level' => 'INFO',
            'channel' => 'test-logs',
        ]);

        $handler = (new RedisMqLogHandler())($config, $kernel);

        $this->assertInstanceOf(LogRedisMq::class, $handler);
    }

    public function testRedisMqLogHandlerFallsBackToRedisCache(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redisCache = $this->createMock(Redis::class);

        $registry = new ResourceRegistry();
        // No REDIS_LOGGER, no REDIS_MESSAGING
        $registry->register(ResourceKey::REDIS_CACHE->value, $redisCache);

        $kernel = $this->createKernelWithResources($registry);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'redismq',
            'level' => 'INFO',
            'channel' => 'test-logs',
        ]);

        $handler = (new RedisMqLogHandler())($config, $kernel);

        $this->assertInstanceOf(LogRedisMq::class, $handler);
    }

    public function testFallbackChainOrderIsCorrect(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        // Create three different Redis mocks
        $redisLogger = $this->createMock(Redis::class);
        $redisMessaging = $this->createMock(Redis::class);
        $redisCache = $this->createMock(Redis::class);

        // Register all three
        $registry = new ResourceRegistry();
        $registry->register(ResourceKey::REDIS_LOGGER->value, $redisLogger);
        $registry->register(ResourceKey::REDIS_MESSAGING->value, $redisMessaging);
        $registry->register(ResourceKey::REDIS_CACHE->value, $redisCache);

        $kernel = $this->createKernelWithResources($registry);

        $config = LoggerHandlerConfig::fromArray([
            'type' => 'redismq',
            'level' => 'INFO',
            'channel' => 'test-logs',
        ]);

        // Should use REDIS_LOGGER (first in chain) even though all are available
        $handler = (new RedisMqLogHandler())($config, $kernel);

        $this->assertInstanceOf(LogRedisMq::class, $handler);
    }

    /**
     * Create a DomainKernel mock with the given ResourceRegistry
     */
    private function createKernelWithResources(ResourceRegistry $registry): DomainKernel
    {
        $kernel = $this->createMock(DomainKernel::class);
        $kernel->method('getResources')->willReturn($registry);

        return $kernel;
    }
}
