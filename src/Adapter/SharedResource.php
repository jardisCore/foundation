<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter;

use AMQPConnection;
use PDO;
use Psr\Container\ContainerInterface;
use RdKafka\KafkaConsumer;
use RdKafka\Producer as KafkaProducer;
use Redis;

/**
 * Shared Resource Provider
 *
 * Provides a static registry for sharing connections across multiple Domain instances.
 * Resources are set once during application bootstrap and remain immutable thereafter.
 *
 * This class enables resource sharing without requiring constructor injection,
 * while preserving lazy initialization for resources not explicitly set.
 *
 * Usage:
 * ======
 *
 * Bootstrap (once, at application start):
 * ```php
 * // Set existing connections to share across domains
 * SharedResource::setPdoWriter($existingPdo);
 * SharedResource::setPdoReader(1, $readerPdo1);
 * SharedResource::setPdoReader(2, $readerPdo2);
 * SharedResource::setRedisCache($redis);
 * ```
 *
 * Domain usage (automatic):
 * ```php
 * $domain = new OrderDomain();
 * $domain->boundedContext()->Command()...
 * // Domain automatically uses shared connections
 * ```
 *
 * Behavior:
 * - If a resource is set: Domain uses the shared connection
 * - If a resource is NOT set: Domain creates its own via ENV configuration (lazy)
 */
class SharedResource
{
    private static ?ResourceRegistry $registry = null;

    /**
     * Get a copy of the registry for use in Domain classes.
     *
     * Always returns a copy to prevent accidental modification of the original.
     * Each Domain gets its own registry instance that can be freely modified.
     */
    public static function registry(): ResourceRegistry
    {
        $copy = new ResourceRegistry();
        foreach (self::getRegistry()->all() as $key => $value) {
            $copy->register($key, $value);
        }
        return $copy;
    }

    /**
     * Reset the registry (for testing only).
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$registry = null;
    }

    // =========================================================================
    // Database Connections
    // =========================================================================

    /**
     * Set the PDO writer connection (only if not already set).
     */
    public static function setPdoWriter(PDO $pdo): void
    {
        self::getRegistry()->has(ResourceKey::PDO_WRITER->value)
            || self::getRegistry()->register(ResourceKey::PDO_WRITER->value, $pdo);
    }

    /**
     * Set a PDO reader connection (only if not already set).
     *
     * @param int $index Reader index (1, 2, 3, ...)
     */
    public static function setPdoReader(int $index, PDO $pdo): void
    {
        $key = ResourceKey::pdoReader($index);
        self::getRegistry()->has($key) || self::getRegistry()->register($key, $pdo);
    }

    /**
     * Set the PDO connection for cache database layer (only if not already set).
     */
    public static function setPdoCache(PDO $pdo): void
    {
        self::getRegistry()->has(ResourceKey::PDO_CACHE->value)
            || self::getRegistry()->register(ResourceKey::PDO_CACHE->value, $pdo);
    }

    // =========================================================================
    // Redis Connections
    // =========================================================================

    /**
     * Set the Redis connection for caching (only if not already set).
     */
    public static function setRedisCache(Redis $redis): void
    {
        self::getRegistry()->has(ResourceKey::REDIS_CACHE->value)
            || self::getRegistry()->register(ResourceKey::REDIS_CACHE->value, $redis);
    }

    /**
     * Set the Redis connection for messaging (only if not already set).
     */
    public static function setRedisMessaging(Redis $redis): void
    {
        self::getRegistry()->has(ResourceKey::REDIS_MESSAGING->value)
            || self::getRegistry()->register(ResourceKey::REDIS_MESSAGING->value, $redis);
    }

    /**
     * Set the Redis connection for logging (only if not already set).
     */
    public static function setRedisLogger(Redis $redis): void
    {
        self::getRegistry()->has(ResourceKey::REDIS_LOGGER->value)
            || self::getRegistry()->register(ResourceKey::REDIS_LOGGER->value, $redis);
    }

    // =========================================================================
    // Kafka Connections
    // =========================================================================

    /**
     * Set the Kafka producer (only if not already set).
     */
    public static function setKafkaProducer(KafkaProducer $producer): void
    {
        self::getRegistry()->has(ResourceKey::KAFKA_PRODUCER->value)
            || self::getRegistry()->register(ResourceKey::KAFKA_PRODUCER->value, $producer);
    }

    /**
     * Set the Kafka consumer (only if not already set).
     */
    public static function setKafkaConsumer(KafkaConsumer $consumer): void
    {
        self::getRegistry()->has(ResourceKey::KAFKA_CONSUMER->value)
            || self::getRegistry()->register(ResourceKey::KAFKA_CONSUMER->value, $consumer);
    }

    /**
     * Set the Kafka producer for logging (only if not already set).
     */
    public static function setKafkaLogger(KafkaProducer $producer): void
    {
        self::getRegistry()->has(ResourceKey::KAFKA_LOGGER->value)
            || self::getRegistry()->register(ResourceKey::KAFKA_LOGGER->value, $producer);
    }

    // =========================================================================
    // RabbitMQ Connection
    // =========================================================================

    /**
     * Set the AMQP connection for RabbitMQ (only if not already set).
     */
    public static function setAmqp(AMQPConnection $connection): void
    {
        self::getRegistry()->has(ResourceKey::AMQP->value)
            || self::getRegistry()->register(ResourceKey::AMQP->value, $connection);
    }

    /**
     * Set the AMQP connection for logging (only if not already set).
     */
    public static function setAmqpLogger(AMQPConnection $connection): void
    {
        self::getRegistry()->has(ResourceKey::AMQP_LOGGER->value)
            || self::getRegistry()->register(ResourceKey::AMQP_LOGGER->value, $connection);
    }

    // =========================================================================
    // Container
    // =========================================================================

    /**
     * Set the DI container (only if not already set).
     */
    public static function setContainer(ContainerInterface $container): void
    {
        self::getRegistry()->has(ResourceKey::CONTAINER->value)
            || self::getRegistry()->register(ResourceKey::CONTAINER->value, $container);
    }

    /**
     * Get or create the internal registry.
     */
    private static function getRegistry(): ResourceRegistry
    {
        return self::$registry ??= new ResourceRegistry();
    }
}
