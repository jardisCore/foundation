<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter;

use JardisPsr\Foundation\ResourceRegistryInterface;
use RuntimeException;

/**
 * Resource Registry
 *
 * Central registry for external resources (connections, instances) that should
 * be reused instead of creating new ones.
 *
 * Resource Key Conventions:
 * ========================
 *
 * Database Connections:
 * - connection.pdo.writer          - PDO for write operations
 * - connection.pdo.reader1         - PDO for read operations (1-N)
 * - connection.pdo.reader2         - PDO for read operations
 *
 * Cache Connections:
 * - connection.redis.cache         - Redis instance for cache layer
 *
 * Messaging Connections:
 * - connection.redis.messaging     - Redis instance for messaging
 * - connection.kafka.producer      - RdKafka\Producer instance
 * - connection.kafka.consumer      - RdKafka\Consumer instance
 * - connection.amqp                - AMQPConnection instance
 *
 * Logger Handlers:
 * - logger.handler.{name}          - Pre-configured log handler
 *
 * Example Usage:
 * ==============
 *
 * ```php
 * // Legacy application setup
 * $legacyPdo = new PDO('mysql:host=localhost;dbname=legacy', 'user', 'pass');
 * $legacyRedis = new Redis();
 * $legacyRedis->connect('localhost', 6379);
 *
 * // Register for Foundation reuse
 * $registry = new ResourceRegistry();
 * $registry->register('connection.pdo.writer', $legacyPdo);
 * $registry->register('connection.redis.cache', $legacyRedis);
 * $registry->register('connection.redis.messaging', $legacyRedis);
 *
 * // Create domain with shared resources
 * class OrderDomain extends Domain {
 *     protected function getSharedResources(): ?ResourceRegistry {
 *         global $registry;
 *         return $registry;
 *     }
 * }
 *
 * $domain = new OrderDomain();
 * $kernel = $domain->getKernel();
 *
 * // Services automatically reuse external connections!
 * $pool = $kernel->getConnectionPool(); // Uses $legacyPdo
 * $cache = $kernel->getCache();      // Redis layer uses $legacyRedis
 * $msg = $kernel->getMessage();      // Uses $legacyRedis
 * ```
 */
class ResourceRegistry implements ResourceRegistryInterface
{
    /** @var array<string, mixed> */
    private array $resources = [];

    /**
     * Register a resource by key
     *
     * @param string $key Resource identifier (e.g., 'connection.pdo.writer')
     * @param mixed $resource The resource instance (PDO, Redis, etc.)
     */
    public function register(string $key, mixed $resource): void
    {
        $this->resources[$key] = $resource;
    }

    /**
     * Check if a resource exists
     *
     * @param string $key Resource identifier
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->resources);
    }

    /**
     * Get a resource by key
     *
     * @param string $key Resource identifier
     * @return mixed The resource instance
     * @throws RuntimeException If resource not found
     */
    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new RuntimeException("Resource '{$key}' not registered");
        }

        return $this->resources[$key];
    }

    /**
     * Get all registered resources
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->resources;
    }

    /**
     * Remove a resource
     *
     * @param string $key Resource identifier
     */
    public function unregister(string $key): void
    {
        unset($this->resources[$key]);
    }
}
