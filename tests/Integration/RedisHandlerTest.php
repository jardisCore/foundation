<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration;

use JardisCore\Foundation\Handler\RedisHandler;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * Integration tests for RedisHandler against real Redis service.
 */
class RedisHandlerTest extends TestCase
{
    public function testReturnsRedisInstance(): void
    {
        $handler = new RedisHandler();
        $redis = $handler($this->redisEnv());

        self::assertInstanceOf(Redis::class, $redis);
    }

    public function testRedisCanSetAndGet(): void
    {
        $handler = new RedisHandler();
        $redis = $handler($this->redisEnv());

        self::assertInstanceOf(Redis::class, $redis);

        $redis->set('integration_test_key', 'hello');
        self::assertSame('hello', $redis->get('integration_test_key'));

        $redis->del('integration_test_key');
    }

    public function testRedisSelectsDatabase(): void
    {
        $handler = new RedisHandler();
        $env = [
            'redis_host' => $_ENV['redis_host'] ?? 'redis_test',
            'redis_port' => $_ENV['redis_port'] ?? '6379',
            'redis_password' => '',
            'redis_database' => '1',
        ];

        $redis = $handler($this->closureFrom($env));

        self::assertInstanceOf(Redis::class, $redis);

        $redis->set('db1_test', 'in_db1');
        self::assertSame('in_db1', $redis->get('db1_test'));

        $redis->del('db1_test');
    }

    public function testRedisWithPasswordAgainstNoAuthServerReturnsNull(): void
    {
        $handler = new RedisHandler();

        // Even when Redis runs without requirepass, the ext-redis extension throws
        // a RedisException when AUTH is called with a non-empty password against a
        // server that has no password configured. The handler catches the exception
        // and returns null (graceful degradation).
        $env = [
            'redis_host' => $_ENV['redis_host'] ?? 'redis_test',
            'redis_port' => $_ENV['redis_port'] ?? '6379',
            'redis_password' => 'wrong_password',
        ];

        $result = $handler($this->closureFrom($env));

        self::assertNull($result);
    }

    public function testNoHostReturnsNull(): void
    {
        $handler = new RedisHandler();
        $result = $handler($this->closureFrom([]));

        self::assertNull($result);
    }

    public function testUnreachableHostReturnsNull(): void
    {
        $handler = new RedisHandler();
        $result = $handler($this->closureFrom([
            'redis_host' => 'nonexistent_host_that_does_not_exist',
            'redis_port' => '6379',
        ]));

        self::assertNull($result);
    }

    public function testCustomPrefixWorks(): void
    {
        $handler = new RedisHandler();
        $env = [
            'custom_host' => $_ENV['redis_host'] ?? 'redis_test',
            'custom_port' => $_ENV['redis_port'] ?? '6379',
        ];

        $redis = $handler($this->closureFrom($env), 'custom_');

        self::assertInstanceOf(Redis::class, $redis);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /** @return \Closure(string): mixed */
    private function redisEnv(): \Closure
    {
        return $this->closureFrom([
            'redis_host' => $_ENV['redis_host'] ?? 'redis_test',
            'redis_port' => $_ENV['redis_port'] ?? '6379',
            'redis_password' => $_ENV['redis_password'] ?? '',
            'redis_database' => $_ENV['redis_database'] ?? '0',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return \Closure(string): mixed
     */
    private function closureFrom(array $data): \Closure
    {
        return static fn (string $key): mixed => $data[strtolower($key)] ?? null;
    }
}
