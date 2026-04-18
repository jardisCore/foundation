<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Handler;

use Closure;
use JardisCore\Foundation\Data\CacheLayer;
use JardisAdapter\Cache\Adapter\CacheApcu;
use JardisAdapter\Cache\Adapter\CacheDatabase;
use JardisAdapter\Cache\Adapter\CacheMemory;
use JardisAdapter\Cache\Adapter\CacheRedis;
use JardisAdapter\Cache\Cache;
use PDO;
use Psr\SimpleCache\CacheInterface;
use Redis;

/**
 * Builds a PSR-16 cache from ENV values.
 *
 * Requires jardisadapter/cache. Layer order defined by CACHE_LAYERS (comma-separated).
 * Example: CACHE_LAYERS=memory,redis,db
 */
final class CacheHandler
{
    /** @param Closure(string): mixed $env */
    public function __invoke(Closure $env, ?PDO $pdo = null, ?Redis $redis = null): ?CacheInterface
    {
        if (!class_exists(Cache::class)) {
            return null;
        }

        $namespace = $env('cache_namespace') !== null
            ? (string) $env('cache_namespace')
            : null;

        $layerNames = $env('cache_layers') !== null
            ? array_map('trim', explode(',', (string) $env('cache_layers')))
            : [];

        $layers = [];

        foreach ($layerNames as $name) {
            $type = CacheLayer::tryFrom($name);
            if ($type === null) {
                continue;
            }

            try {
                $layer = match ($type) {
                    CacheLayer::Memory => new CacheMemory($namespace),
                    CacheLayer::Apcu => new CacheApcu($namespace),
                    CacheLayer::Redis => $redis !== null ? new CacheRedis($redis, $namespace) : null,
                    CacheLayer::Database => $pdo !== null
                        ? new CacheDatabase($pdo, (string) ($env('cache_db_table') ?? 'cache'), namespace: $namespace)
                        : null,
                };
            } catch (\Throwable) {
                continue;
            }

            if ($layer !== null) {
                $layers[] = $layer;
            }
        }

        return new Cache($layers);
    }
}
