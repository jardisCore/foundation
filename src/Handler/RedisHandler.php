<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Handler;

use Closure;
use Redis;
use RedisException;

/**
 * Builds a Redis connection from ENV values.
 *
 * Shared between cache and logger handlers.
 */
final class RedisHandler
{
    /** @param Closure(string): mixed $env */
    public function __invoke(Closure $env, string $prefix = 'redis_'): ?Redis
    {
        if ($env($prefix . 'host') === null) {
            return null;
        }

        try {
            $redis = new Redis();
            $redis->connect(
                (string) $env($prefix . 'host'),
                (int) ($env($prefix . 'port') ?? 6379),
            );

            $password = $env($prefix . 'password');
            if ($password !== null && $password !== '') {
                $redis->auth((string) $password);
            }

            $database = $env($prefix . 'database');
            if ($database !== null) {
                $redis->select((int) $database);
            }

            return $redis;
        } catch (RedisException) {
            return null;
        }
    }
}
