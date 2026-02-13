<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Cache;

use Exception;
use JardisCore\Foundation\Adapter\Cache\Handler\ApcuCacheHandler;
use JardisCore\Foundation\Adapter\Cache\Handler\DatabaseCacheHandler;
use JardisCore\Foundation\Adapter\Cache\Handler\MemoryCacheHandler;
use JardisCore\Foundation\Adapter\Cache\Handler\RedisCacheHandler;
use JardisAdapter\Cache\Cache;
use JardisPsr\Foundation\DomainKernelInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Initialize Cache Service
 *
 * Responsibility: Orchestrate cache layer registration and build Cache service.
 *
 * Determines which cache layers to instantiate based on configuration:
 * - Checks ENV configuration for each layer
 * - Only instantiates handlers when actually configured (Lazy Loading)
 *
 * Cache layers (in priority order):
 * - Memory (L1): Request-scoped memory cache (enabled by default)
 * - APCu (L2): Process-scoped APCu cache
 * - Redis (L3): Distributed Redis cache
 * - Database (L4): Persistent database cache
 *
 * Each handler supports:
 * 1. External resources from ResourceRegistry
 * 2. ENV-based configuration
 */
class InitCache
{
    /**
     * Initialize cache from .env configuration.
     *
     * Returns null if no cache layers are configured.
     * Only instantiates handlers that are enabled (lazy loading).
     *
     * @throws Exception
     */
    public function __invoke(DomainKernelInterface $kernel): ?CacheInterface
    {
        $namespace = $kernel->getEnv('CACHE_NAMESPACE') ?? 'app';
        $layers = [];

        // Lazy: Only instantiate enabled cache layers
        $this->registerCacheHandlers($kernel, $namespace, $layers);

        return empty($layers) ? null : new Cache(...$layers);
    }

    /**
     * Register all enabled cache handlers.
     *
     * Handlers are registered in order of priority (L1 -> L2 -> L3 -> L4).
     *
     * @param array<int, CacheInterface> $layers
     */
    private function registerCacheHandlers(DomainKernelInterface $kernel, string $namespace, array &$layers): void
    {
        // L1: Memory cache (enabled by default unless explicitly disabled)
        if ($kernel->getEnv('CACHE_MEMORY_ENABLED') !== false) {
            $layers[] = (new MemoryCacheHandler())($kernel, $namespace);
        }

        // L2: APCu cache
        if ($kernel->getEnv('CACHE_APCU_ENABLED')) {
            $apcuCache = (new ApcuCacheHandler())($kernel, $namespace);
            if ($apcuCache !== null) {
                $layers[] = $apcuCache;
            }
        }

        // L3: Redis cache
        if ($kernel->getEnv('CACHE_REDIS_ENABLED')) {
            $redisCache = (new RedisCacheHandler())($kernel, $namespace);
            if ($redisCache !== null) {
                $layers[] = $redisCache;
            }
        }

        // L4: Database cache
        if ($kernel->getEnv('CACHE_DB_ENABLED')) {
            $dbCache = (new DatabaseCacheHandler())($kernel, $namespace);
            if ($dbCache !== null) {
                $layers[] = $dbCache;
            }
        }
    }
}
