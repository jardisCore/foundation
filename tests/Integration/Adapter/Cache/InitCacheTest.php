<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Cache;

use JardisCore\Foundation\Adapter\Cache\InitCache;
use JardisCore\Foundation\Adapter\ConnectionProvider;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Redis;

/**
 * Integration Tests for InitCache
 *
 * Tests cache initialization with ConnectionProvider and config arrays.
 */
class InitCacheTest extends TestCase
{
    public function testCreatesMultiLayerCacheWithRedis(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $connections = new ConnectionProvider();

        try {
            $redis = new Redis();
            $redis->connect('redis', 6379, 2.5);
            $connections->addRedis('cache', $redis);
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }

        $initCache = new InitCache();
        $cache = $initCache(
            $connections,
            ['namespace' => 'integration_test', 'memory_enabled' => true]
        );

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    public function testReturnsNullWhenNoCacheEnabled(): void
    {
        $connections = new ConnectionProvider();

        $initCache = new InitCache();
        $cache = $initCache($connections, [
            'memory_enabled' => false,
            'apcu_enabled' => false,
            'db_enabled' => false,
        ]);

        $this->assertNull($cache);
    }

    public function testCreatesMemoryCacheOnly(): void
    {
        $connections = new ConnectionProvider();

        $initCache = new InitCache();
        $cache = $initCache($connections, [
            'namespace' => 'memory_test',
            'memory_enabled' => true,
        ]);

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    public function testUsesDefaultNamespace(): void
    {
        $connections = new ConnectionProvider();

        $initCache = new InitCache();
        $cache = $initCache($connections, ['memory_enabled' => true]);

        $this->assertNotNull($cache);
    }

    public function testCreatesApcuCacheWhenEnabled(): void
    {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            $this->markTestSkipped('APCu extension is not available or not enabled');
        }

        $connections = new ConnectionProvider();

        $initCache = new InitCache();
        $cache = $initCache($connections, [
            'memory_enabled' => true,
            'apcu_enabled' => true,
        ]);

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    public function testCreatesDatabaseCacheWhenEnabled(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE IF NOT EXISTS cache (key TEXT PRIMARY KEY, value TEXT, ttl INTEGER)');

        $connections = new ConnectionProvider();
        $connections->addPdo('writer', $pdo);

        $initCache = new InitCache();
        $cache = $initCache($connections, [
            'memory_enabled' => true,
            'db_enabled' => true,
            'db_table' => 'cache',
        ]);

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    public function testCreatesThreeLayerCache(): void
    {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            $this->markTestSkipped('APCu extension is not available or not enabled');
        }

        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $connections = new ConnectionProvider();

        try {
            $redis = new Redis();
            $redis->connect('redis', 6379, 2.5);
            $connections->addRedis('cache', $redis);
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }

        $initCache = new InitCache();
        $cache = $initCache($connections, [
            'memory_enabled' => true,
            'apcu_enabled' => true,
        ]);

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }
}
