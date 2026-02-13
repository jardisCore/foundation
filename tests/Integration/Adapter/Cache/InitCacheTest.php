<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Cache;

use JardisCore\Foundation\Adapter\Cache\InitCache;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class InitCacheTest extends TestCase
{
    public function testCreatesMultiLayerCacheWithRedis(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'CACHE_NAMESPACE' => 'integration_test',
            'CACHE_MEMORY_ENABLED' => true,
            'CACHE_REDIS_ENABLED' => true,
            'CACHE_REDIS_HOST' => 'redis',
            'CACHE_REDIS_PORT' => '6379',
            'CACHE_REDIS_PASSWORD' => '',
            'CACHE_REDIS_DATABASE' => '0'
        ]);

        $initCache = new InitCache();
        $cache = $initCache->__invoke($kernel);

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    public function testReturnsNullWhenNoCacheEnabled(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'CACHE_MEMORY_ENABLED' => false,
            'CACHE_APCU_ENABLED' => false,
            'CACHE_REDIS_ENABLED' => false,
            'CACHE_DB_ENABLED' => false
        ]);

        $initCache = new InitCache();
        $cache = $initCache->__invoke($kernel);

        $this->assertNull($cache);
    }

    public function testCreatesMemoryCacheOnly(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'CACHE_NAMESPACE' => 'memory_test',
            'CACHE_MEMORY_ENABLED' => true
        ]);

        $initCache = new InitCache();
        $cache = $initCache->__invoke($kernel);

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    public function testUsesDefaultNamespace(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'CACHE_MEMORY_ENABLED' => true
        ]);

        $initCache = new InitCache();
        $cache = $initCache->__invoke($kernel);

        $this->assertNotNull($cache);
    }

    public function testCreatesApcuCacheWhenEnabled(): void
    {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            $this->markTestSkipped('APCu extension is not available or not enabled');
        }

        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'CACHE_MEMORY_ENABLED' => true,
            'CACHE_APCU_ENABLED' => true,
            'CACHE_REDIS_ENABLED' => false,
            'CACHE_DB_ENABLED' => false
        ]);

        $initCache = new InitCache();
        $cache = $initCache->__invoke($kernel);

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    public function testCreatesDatabaseCacheWhenEnabled(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'CACHE_MEMORY_ENABLED' => true,
            'CACHE_APCU_ENABLED' => false,
            'CACHE_REDIS_ENABLED' => false,
            'CACHE_DB_ENABLED' => true,
            'CACHE_DB_TABLE' => 'cache'
        ]);

        $initCache = new InitCache();
        $cache = $initCache->__invoke($kernel);

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    public function testCreatesThreeLayerCache(): void
    {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            $this->markTestSkipped('APCu extension is not available or not enabled');
        }

        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'CACHE_MEMORY_ENABLED' => true,
            'CACHE_APCU_ENABLED' => true,
            'CACHE_REDIS_ENABLED' => true,
            'CACHE_REDIS_HOST' => 'redis_test',
            'CACHE_DB_ENABLED' => false
        ]);

        $initCache = new InitCache();
        $cache = $initCache->__invoke($kernel);

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }
}
