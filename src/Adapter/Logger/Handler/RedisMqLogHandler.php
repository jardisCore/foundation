<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use Exception;
use InvalidArgumentException;
use JardisCore\Foundation\Adapter\ResourceKey;
use JardisCore\Foundation\Adapter\SharedResource;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogRedisMq;
use JardisPsr\Foundation\DomainKernelInterface;
use Redis;
use RedisException;

/**
 * Redis Message Queue Log Handler
 *
 * Creates RedisMQ log handler for message queue based logging.
 *
 * Connection resolution (fallback chain):
 * 1. REDIS_LOGGER - Dedicated logger Redis connection
 * 2. REDIS_MESSAGING - Reuse messaging Redis connection
 * 3. REDIS_CACHE - Reuse cache Redis connection
 * 4. ENV-based - Creates new Redis connection (registered as REDIS_LOGGER)
 *
 * Required options: 'channel'
 * Optional options: 'host' (default: 'localhost'), 'port' (default: 6379), 'password', 'database'
 *
 * Example ENV configuration:
 * LOG_HANDLER5_TYPE=redismq
 * LOG_HANDLER5_CHANNEL=logs
 * LOG_HANDLER5_HOST=redis-logs.example.com
 * LOG_HANDLER5_PORT=6379
 * LOG_HANDLER5_PASSWORD=log_password
 * LOG_HANDLER5_DATABASE=0
 */
class RedisMqLogHandler
{
    public function __invoke(LoggerHandlerConfig $config, DomainKernelInterface $kernel): LogCommandInterface
    {
        // Get configuration
        $channel = $config->getOption('channel', 'logs');

        if (!is_string($channel) || trim($channel) === '') {
            throw new InvalidArgumentException('Redis channel must be a non-empty string');
        }

        // ===== Fallback Chain: REDIS_LOGGER → REDIS_MESSAGING → REDIS_CACHE → ENV =====
        $redis = $this->resolveRedisConnection($kernel);

        if ($redis !== null) {
            return new LogRedisMq($redis, $channel);
        }

        // ===== Create new Redis Connection from ENV =====
        $host = $config->getOption('host', 'localhost');
        $portValue = $config->getOption('port', 6379);
        $port = is_numeric($portValue) ? (int) $portValue : 6379;
        $password = $config->getOption('password');
        $databaseValue = $config->getOption('database', 0);
        $database = is_numeric($databaseValue) ? (int) $databaseValue : 0;

        if (!is_string($host)) {
            throw new InvalidArgumentException('Redis host must be a string');
        }

        $redis = new Redis();

        try {
            $connected = $redis->connect($host, $port, 2.0);
            if (!$connected) {
                throw new InvalidArgumentException("Failed to connect to Redis at {$host}:{$port}");
            }

            if (is_string($password) && $password !== '') {
                if (!$redis->auth($password)) {
                    throw new InvalidArgumentException('Redis authentication failed');
                }
            }

            $redis->select($database);
        } catch (RedisException $e) {
            throw new InvalidArgumentException(
                "Redis connection error at {$host}:{$port}: {$e->getMessage()}",
                previous: $e
            );
        }

        // Register new connection for cross-domain reuse
        SharedResource::setRedisLogger($redis);

        return new LogRedisMq($redis, $channel);
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
