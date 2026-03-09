<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Cache;

use JardisAdapter\Cache\Adapter\CacheApcu;
use JardisAdapter\Cache\Adapter\CacheDatabase;
use JardisAdapter\Cache\Adapter\CacheMemory;
use JardisAdapter\Cache\Adapter\CacheRedis;
use JardisAdapter\Cache\Cache;
use JardisCore\Foundation\Adapter\ConnectionProvider;
use Psr\SimpleCache\CacheInterface;

/**
 * Initialize Cache Service
 *
 * Assembles cache layers from pre-resolved connections.
 * No ENV reading, no connection creation — pure assembly.
 *
 * Cache layers (in priority order):
 * - Memory (L1): Request-scoped memory cache
 * - APCu (L2): Process-scoped APCu cache
 * - Redis (L3): Distributed Redis cache
 * - Database (L4): Persistent database cache
 */
class InitCache
{
    /**
     * @param array<string, mixed> $config Cache configuration from ENV
     */
    public function __invoke(ConnectionProvider $connections, array $config): ?CacheInterface
    {
        $namespace = (string) ($config['namespace'] ?? 'app');
        $layers = [];

        // L1: Memory (enabled by default unless explicitly disabled)
        if ($config['memory_enabled'] ?? true) {
            $layers[] = new CacheMemory($namespace);
        }

        // L2: APCu
        if (($config['apcu_enabled'] ?? false) && extension_loaded('apcu')) {
            $layers[] = new CacheApcu($namespace);
        }

        // L3: Redis
        if ($connections->hasRedis('cache')) {
            $redis = $connections->redis('cache');
            if ($redis !== null) {
                $layers[] = new CacheRedis($redis, $namespace);
            }
        }

        // L4: Database (use dedicated cache PDO or fall back to writer)
        if ($config['db_enabled'] ?? false) {
            $pdo = $connections->pdo('cache') ?? $connections->pdo('writer');
            if ($pdo !== null) {
                $table = (string) ($config['db_table'] ?? 'cache');
                $layers[] = new CacheDatabase($pdo, $namespace, $table);
            }
        }

        return $layers !== [] ? new Cache(...$layers) : null;
    }
}
