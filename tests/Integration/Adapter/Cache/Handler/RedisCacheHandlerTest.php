<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Cache\Handler;

use JardisCore\Foundation\Adapter\Cache\Handler\RedisCacheHandler;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisAdapter\Cache\Adapter\CacheRedis;
use PHPUnit\Framework\TestCase;

class RedisCacheHandlerTest extends TestCase
{
    public function testCreatesRedisCacheInstance(): void
    {
        $handler = new RedisCacheHandler();
        $kernel = TestKernelFactory::create();

        $cache = $handler->__invoke($kernel, 'test_namespace');

        $this->assertNotNull($cache, 'Redis cache should be created');
        $this->assertInstanceOf(CacheRedis::class, $cache);
    }

    public function testReturnsNullWhenRedisHostMissing(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'CACHE_REDIS_HOST' => null
        ]);

        $handler = new RedisCacheHandler();
        $cache = $handler->__invoke($kernel, 'test');

        $this->assertNull($cache);
    }

    public function testHandlesRedisWithPasswordPath(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'CACHE_REDIS_HOST' => 'redis',
            'CACHE_REDIS_PORT' => '6379',
            'CACHE_REDIS_PASSWORD' => null,
            'CACHE_REDIS_DATABASE' => '0'
        ]);

        $handler = new RedisCacheHandler();
        $cache = $handler->__invoke($kernel, 'test_no_auth');

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheRedis::class, $cache);
    }

    public function testHandlesRedisDatabaseSelection(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'CACHE_REDIS_HOST' => 'redis',
            'CACHE_REDIS_PORT' => '6379',
            'CACHE_REDIS_PASSWORD' => '',
            'CACHE_REDIS_DATABASE' => '1'
        ]);

        $handler = new RedisCacheHandler();
        $cache = $handler->__invoke($kernel, 'test_db_1');

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheRedis::class, $cache);
    }
}
