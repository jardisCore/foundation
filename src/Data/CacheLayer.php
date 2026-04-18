<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Data;

/**
 * Available cache layer types for CACHE_LAYERS configuration.
 */
enum CacheLayer: string
{
    case Memory = 'memory';
    case Apcu = 'apcu';
    case Redis = 'redis';
    case Database = 'db';
}
