<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Cache\Handler;

use Exception;
use JardisAdapter\Cache\Adapter\CacheRedis;
use JardisCore\Foundation\Adapter\ResourceKey;
use JardisPsr\Foundation\DomainKernelInterface;
use Redis;

/**
 * Redis Cache Handler (L3)
 *
 * Redis cache for distributed caching.
 * Note: InitCache checks CACHE_REDIS_ENABLED before calling this handler.
 *
 * Supports two modes:
 * 1. External Redis: Uses pre-existing Redis instance from ResourceRegistry (connection.redis.cache)
 * 2. ENV-based: Creates new Redis connection from environment variables
 *
 * Environment variables (for ENV-based mode):
 * - CACHE_REDIS_HOST: Redis host (required)
 * - CACHE_REDIS_PORT: Redis port (default: 6379)
 * - CACHE_REDIS_PASSWORD: Redis password (optional)
 * - CACHE_REDIS_DATABASE: Redis database number (optional)
 * - CACHE_NAMESPACE: Cache namespace (default: app)
 */
class RedisCacheHandler
{
    public function __invoke(DomainKernelInterface $kernel, string $namespace): ?CacheRedis
    {
        $resources = $kernel->getResources();

        // ===== Check for External Redis First =====
        if ($resources->has(ResourceKey::REDIS_CACHE->value)) {
            $redis = $resources->get(ResourceKey::REDIS_CACHE->value);

            if (!$redis instanceof Redis) {
                throw new Exception(
                    'Resource "connection.redis.cache" must be Redis instance, got ' .
                    get_debug_type($redis)
                );
            }

            return new CacheRedis($redis, namespace: $namespace);
        }

        // ===== Fallback to ENV-based Redis Connection =====
        $host = $kernel->getEnv('CACHE_REDIS_HOST');
        if (!$host) {
            return null;
        }

        $redis = new Redis();
        $redis->connect(
            $host,
            (int) ($kernel->getEnv('CACHE_REDIS_PORT') ?? 6379)
        );

        if ($password = $kernel->getEnv('CACHE_REDIS_PASSWORD')) {
            $redis->auth($password);
        }

        if ($database = $kernel->getEnv('CACHE_REDIS_DATABASE')) {
            $redis->select((int) $database);
        }

        return new CacheRedis($redis, namespace: $namespace);
    }
}
