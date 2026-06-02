<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration;

use JardisCore\Foundation\Handler\CacheHandler;
use JardisCore\Foundation\Handler\ConnectionHandler;
use JardisCore\Foundation\Handler\RedisHandler;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Redis;

/**
 * Integration tests for CacheHandler with real Redis and database services.
 */
class CacheHandlerTest extends TestCase
{
    public function testMemoryLayerOnly(): void
    {
        $handler = new CacheHandler();
        $cache = $handler($this->env(['cache_layers' => 'memory']));

        self::assertInstanceOf(CacheInterface::class, $cache);

        $cache->set('mem_test', 'works');
        self::assertSame('works', $cache->get('mem_test'));
    }

    public function testApcuLayer(): void
    {
        $handler = new CacheHandler();
        $cache = $handler($this->env(['cache_layers' => 'apcu', 'cache_namespace' => 'int_test']));

        self::assertInstanceOf(CacheInterface::class, $cache);

        $cache->set('apcu_test', 'fast');
        self::assertSame('fast', $cache->get('apcu_test'));

        $cache->delete('apcu_test');
    }

    public function testRedisLayer(): void
    {
        $handler = new CacheHandler();
        $redis = $this->buildRedis();
        $cache = $handler($this->env(['cache_layers' => 'redis', 'cache_namespace' => 'int_test']), null, $redis);

        self::assertInstanceOf(CacheInterface::class, $cache);

        $cache->set('redis_test', 'cached');
        self::assertSame('cached', $cache->get('redis_test'));

        $cache->delete('redis_test');
    }

    public function testMemoryAndRedisLayers(): void
    {
        $handler = new CacheHandler();
        $redis = $this->buildRedis();
        $cache = $handler(
            $this->env(['cache_layers' => 'memory,redis', 'cache_namespace' => 'int_test']),
            null,
            $redis,
        );

        self::assertInstanceOf(CacheInterface::class, $cache);

        $cache->set('multi_test', 'multi');
        self::assertSame('multi', $cache->get('multi_test'));

        $cache->delete('multi_test');
    }

    public function testDatabaseLayer(): void
    {
        $pdo = $this->buildMysqlPdo();
        $this->ensureCacheTable($pdo);

        $handler = new CacheHandler();
        $cache = $handler(
            $this->env(['cache_layers' => 'db', 'cache_db_table' => 'cache', 'cache_namespace' => 'int_test']),
            $pdo,
        );

        self::assertInstanceOf(CacheInterface::class, $cache);

        $cache->set('db_test', 'from_db');
        self::assertSame('from_db', $cache->get('db_test'));

        $cache->delete('db_test');
    }

    public function testAllLayersCombined(): void
    {
        $pdo = $this->buildMysqlPdo();
        $this->ensureCacheTable($pdo);
        $redis = $this->buildRedis();

        $handler = new CacheHandler();
        $cache = $handler(
            $this->env([
                'cache_layers' => 'memory,redis,db',
                'cache_namespace' => 'int_test',
                'cache_db_table' => 'cache',
            ]),
            $pdo,
            $redis,
        );

        self::assertInstanceOf(CacheInterface::class, $cache);

        $cache->set('all_layers', 'everywhere');
        self::assertSame('everywhere', $cache->get('all_layers'));

        $cache->delete('all_layers');
    }

    public function testEmptyLayersReturnsEmptyCache(): void
    {
        $handler = new CacheHandler();
        $cache = $handler($this->env([]));

        self::assertInstanceOf(CacheInterface::class, $cache);
        self::assertNull($cache->get('nonexistent'));
    }

    public function testDbLayerSkippedWithoutPdo(): void
    {
        $handler = new CacheHandler();
        $cache = $handler($this->env(['cache_layers' => 'db']));

        self::assertInstanceOf(CacheInterface::class, $cache);
    }

    public function testRedisLayerSkippedWithoutRedis(): void
    {
        $handler = new CacheHandler();
        $cache = $handler($this->env(['cache_layers' => 'redis']));

        self::assertInstanceOf(CacheInterface::class, $cache);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     * @return \Closure(string): mixed
     */
    private function env(array $data): \Closure
    {
        return static fn (string $key): mixed => $data[strtolower($key)] ?? null;
    }

    private function buildRedis(): Redis
    {
        $redis = new Redis();
        $redis->connect($_ENV['redis_host'] ?? 'redis_test', (int) ($_ENV['redis_port'] ?? 6379));

        return $redis;
    }

    private function buildMysqlPdo(): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $_ENV['db_host'] ?? 'mysql_test',
                $_ENV['db_port'] ?? '3306',
                $_ENV['db_database'] ?? 'test_db',
            ),
            $_ENV['db_user'] ?? 'app_user',
            $_ENV['db_password'] ?? 'app_secret',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private function ensureCacheTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS cache (
            cache_key VARCHAR(255) NOT NULL PRIMARY KEY,
            cache_value LONGBLOB,
            expires_at INT UNSIGNED DEFAULT NULL
        )');
    }
}
