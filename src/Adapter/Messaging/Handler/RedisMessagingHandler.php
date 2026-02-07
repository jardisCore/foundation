<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Messaging\Handler;

use Exception;
use JardisAdapter\Messaging\Connection\ExternalRedisConnection;
use JardisAdapter\Messaging\Consumer\RedisConsumer;
use JardisAdapter\Messaging\MessageConsumer;
use JardisAdapter\Messaging\MessagePublisher;
use JardisAdapter\Messaging\Publisher\RedisPublisher;
use JardisCore\Foundation\Adapter\ResourceKey;
use JardisPsr\Foundation\DomainKernelInterface;
use Redis;

/**
 * Redis Messaging Handler (Priority 0)
 *
 * Responsibility: Configure Redis messaging for both Publisher and Consumer.
 * Supports two modes:
 * 1. External Redis: Reuses existing Redis instance from ResourceRegistry (connection.redis.messaging)
 * 2. ENV-based: Creates new Redis connection from environment variables
 *
 * Supports both Redis Pub/Sub and Redis Streams.
 *
 * Environment variables:
 * - MESSAGING_REDIS_ENABLED: Enable Redis messaging (default: false)
 * - MESSAGING_REDIS_HOST: Redis host (required for ENV mode)
 * - MESSAGING_REDIS_PORT: Redis port (default: 6379)
 * - MESSAGING_REDIS_PASSWORD: Redis password (optional)
 * - MESSAGING_REDIS_USE_STREAMS: Use Redis Streams instead of Pub/Sub (default: false)
 */
class RedisMessagingHandler
{
    /**
     * Configure Redis messaging for publisher and consumer.
     *
     * @throws Exception If external Redis resource is invalid type
     */
    public function __invoke(
        DomainKernelInterface $kernel,
        MessagePublisher $publisher,
        MessageConsumer $consumer
    ): void {
        $resources = $kernel->getResources();

        // ===== External Redis First =====
        if ($resources->has(ResourceKey::REDIS_MESSAGING->value)) {
            $this->configureExternalRedis($kernel, $publisher, $consumer, $resources);
            return;
        }

        // ===== ENV-based Redis Fallback =====
        if ($kernel->getEnv('MESSAGING_REDIS_ENABLED')) {
            $this->configureEnvRedis($kernel, $publisher, $consumer);
        }
    }

    /**
     * Configure Redis using external connection from ResourceRegistry.
     *
     * @param \JardisPsr\Foundation\ResourceRegistryInterface $resources
     * @throws Exception If external Redis is not a Redis instance
     */
    private function configureExternalRedis(
        DomainKernelInterface $kernel,
        MessagePublisher $publisher,
        MessageConsumer $consumer,
        $resources
    ): void {
        $externalRedis = $resources->get(ResourceKey::REDIS_MESSAGING->value);

        if (!$externalRedis instanceof Redis) {
            throw new Exception(
                'Resource "connection.redis.messaging" must be Redis instance, got ' .
                get_debug_type($externalRedis)
            );
        }

        $useStreams = (bool) $kernel->getEnv('MESSAGING_REDIS_USE_STREAMS');
        $connection = new ExternalRedisConnection($externalRedis);

        // Configure Publisher
        $redisPublisher = new RedisPublisher($connection, $useStreams);
        $publisher->addPublisher($redisPublisher, 'redis-external', 0);

        // Configure Consumer
        $redisConsumer = new RedisConsumer($connection, $useStreams);
        $consumer->addConsumer($redisConsumer, 'redis-external', 0);
    }

    /**
     * Configure Redis using environment variables.
     */
    private function configureEnvRedis(
        DomainKernelInterface $kernel,
        MessagePublisher $publisher,
        MessageConsumer $consumer
    ): void {
        $host = $kernel->getEnv('MESSAGING_REDIS_HOST');
        if (!$host) {
            return; // No host configured
        }

        $port = (int) ($kernel->getEnv('MESSAGING_REDIS_PORT') ?? 6379);
        $password = $kernel->getEnv('MESSAGING_REDIS_PASSWORD') ?: null;
        $useStreams = (bool) $kernel->getEnv('MESSAGING_REDIS_USE_STREAMS');

        // Configure Publisher
        $publisher->setRedis(
            host: $host,
            port: $port,
            password: $password,
            options: ['useStreams' => $useStreams],
            priority: 0
        );

        // Configure Consumer
        $consumer->setRedis(
            host: $host,
            port: $port,
            password: $password,
            options: ['useStreams' => $useStreams],
            priority: 0
        );
    }
}
