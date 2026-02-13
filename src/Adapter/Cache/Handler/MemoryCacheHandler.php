<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Cache\Handler;

use JardisAdapter\Cache\Adapter\CacheMemory;
use JardisPsr\Foundation\DomainKernelInterface;

/**
 * Memory Cache Handler (L1)
 *
 * In-memory cache for request-scoped caching.
 * Note: InitCache checks CACHE_MEMORY_ENABLED before calling this handler.
 *
 * Environment variables:
 * - CACHE_NAMESPACE: Cache namespace (default: app)
 */
class MemoryCacheHandler
{
    public function __invoke(DomainKernelInterface $kernel, string $namespace): CacheMemory
    {
        return new CacheMemory(namespace: $namespace);
    }
}
