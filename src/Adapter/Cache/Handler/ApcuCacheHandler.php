<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Cache\Handler;

use JardisAdapter\Cache\Adapter\CacheApcu;
use JardisPsr\Foundation\DomainKernelInterface;

/**
 * APCu Cache Handler (L2)
 *
 * APCu cache for process-scoped caching.
 * Note: InitCache checks CACHE_APCU_ENABLED before calling this handler.
 *
 * Environment variables:
 * - CACHE_NAMESPACE: Cache namespace (default: app)
 */
class ApcuCacheHandler
{
    public function __invoke(DomainKernelInterface $kernel, string $namespace): ?CacheApcu
    {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            return null;
        }

        return new CacheApcu(namespace: $namespace);
    }
}
