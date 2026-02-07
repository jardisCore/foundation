<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use Exception;
use InvalidArgumentException;
use JardisCore\Foundation\Adapter\ResourceKey;
use JardisCore\Foundation\Adapter\SharedResource;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogRedis;
use JardisAdapter\Logger\Handler\LogRedisMq;
use JardisPsr\Foundation\DomainKernelInterface;
use Redis;

/**
 * Redis Log Handler
 *
 * Creates Redis log handler from configuration.
 *
 * Connection resolution (fallback chain):
 * 1. REDIS_LOGGER - Dedicated logger Redis connection
 * 2. REDIS_MESSAGING - Reuse messaging Redis connection
 * 3. REDIS_CACHE - Reuse cache Redis connection
 * 4. ENV-based - Creates new Redis connection (registered as REDIS_LOGGER)
 *
 * Optional options: 'host', 'port', 'timeout', 'password', 'database', 'ttl', 'channel'
 *
 * If 'channel' is specified, uses LogRedisMq (message queue), otherwise LogRedis (direct storage).
 */
class RedisLogHandler
{
    public function __invoke(LoggerHandlerConfig $config, DomainKernelInterface $kernel): LogCommandInterface
    {
        // Check if we should use message queue mode
        $channel = $config->getOption('channel');
        $useMessageQueue = is_string($channel) && trim($channel) !== '';

        // ===== Fallback Chain: REDIS_LOGGER → REDIS_MESSAGING → REDIS_CACHE → ENV =====
        $redis = $this->resolveRedisConnection($kernel);

        if ($redis !== null) {
            if ($useMessageQueue) {
                return new LogRedisMq($redis, $channel);
            }

            // LogRedis doesn't support external Redis, so we use LogRedisMq as fallback
            // with a unique channel per log level
            $fallbackChannel = 'logs:' . strtolower($config->level);
            return new LogRedisMq($redis, $fallbackChannel);
        }

        // ===== Create new Redis Connection from ENV =====
        $host = $config->getOption('host', 'localhost');
        $portValue = $config->getOption('port', 6379);
        $port = is_numeric($portValue) ? (int) $portValue : 6379;

        $timeoutValue = $config->getOption('timeout', 2.5);
        $timeout = is_numeric($timeoutValue) ? (float) $timeoutValue : 2.5;

        $password = $config->getOption('password');
        $passwordStr = is_string($password) ? $password : null;

        $databaseValue = $config->getOption('database', 0);
        $database = is_numeric($databaseValue) ? (int) $databaseValue : 0;

        if ($useMessageQueue) {
            $redis = new Redis();
            $redis->connect(is_string($host) ? $host : 'localhost', $port, $timeout);

            if ($passwordStr !== null) {
                $redis->auth($passwordStr);
            }

            $redis->select($database);

            // Register new connection for cross-domain reuse
            SharedResource::setRedisLogger($redis);

            return new LogRedisMq($redis, $channel);
        }

        // Use LogRedis with connection parameters (creates connection internally)
        $ttlValue = $config->getOption('ttl', 3600);
        $ttl = is_numeric($ttlValue) ? (int) $ttlValue : 3600;

        return new LogRedis(
            $config->level,
            is_string($host) ? $host : 'localhost',
            $port,
            $timeout,
            $passwordStr,
            $database,
            $ttl
        );
    }

    /**
     * Resolve Redis connection from fallback chain.
     *
     * @throws Exception If resource exists but is not a Redis instance
     */
    private function resolveRedisConnection(DomainKernelInterface $kernel): ?Redis
    {
        $resources = $kernel->getResources();

        // Fallback chain: REDIS_LOGGER → REDIS_MESSAGING → REDIS_CACHE
        $keys = [
            ResourceKey::REDIS_LOGGER->value,
            ResourceKey::REDIS_MESSAGING->value,
            ResourceKey::REDIS_CACHE->value,
        ];

        foreach ($keys as $key) {
            if ($resources->has($key)) {
                $redis = $resources->get($key);

                if (!$redis instanceof Redis) {
                    throw new Exception(
                        "Resource \"{$key}\" must be Redis instance, got " . get_debug_type($redis)
                    );
                }

                return $redis;
            }
        }

        return null;
    }
}
