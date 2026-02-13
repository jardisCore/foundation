<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Cache\Handler;

use Exception;
use JardisAdapter\Cache\Adapter\CacheDatabase;
use JardisCore\Foundation\Adapter\ResourceKey;
use JardisPsr\Foundation\DomainKernelInterface;
use PDO;

/**
 * Database Cache Handler (L4)
 *
 * Database-backed cache for persistent fallback.
 * Note: InitCache checks CACHE_DB_ENABLED before calling this handler.
 *
 * Supports two modes:
 * 1. External PDO: Uses pre-existing PDO instance from ResourceRegistry (connection.pdo.cache)
 * 2. Fallback: Uses ConnectionPool writer connection
 *
 * Environment variables:
 * - CACHE_DB_TABLE: Database table name (default: cache)
 * - CACHE_NAMESPACE: Cache namespace (default: app)
 */
class DatabaseCacheHandler
{
    /**
     * @throws Exception
     */
    public function __invoke(DomainKernelInterface $kernel, string $namespace): ?CacheDatabase
    {
        $cacheTable = $kernel->getEnv('CACHE_DB_TABLE') ?? 'cache';
        $resources = $kernel->getResources();

        // ===== Check for External Cache PDO First =====
        if ($resources->has(ResourceKey::PDO_CACHE->value)) {
            $pdo = $resources->get(ResourceKey::PDO_CACHE->value);

            if (!$pdo instanceof PDO) {
                throw new Exception(
                    'Resource "connection.pdo.cache" must be PDO instance, got ' .
                    get_debug_type($pdo)
                );
            }

            return new CacheDatabase(
                $pdo,
                namespace: $namespace,
                cacheTable: $cacheTable,
            );
        }

        // ===== Fallback to Database Writer =====
        $pdo = $kernel->getConnectionPool()?->getWriter()?->pdo();

        if ($pdo === null) {
            return null;
        }

        return new CacheDatabase(
            $pdo,
            namespace: $namespace,
            cacheTable: $cacheTable,
        );
    }
}
