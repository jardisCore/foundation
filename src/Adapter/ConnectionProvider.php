<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter;

use AMQPConnection;
use AMQPException;
use Closure;
use InvalidArgumentException;
use PDO;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use Redis;
use RedisException;

/**
 * Central Connection Provider
 *
 * Manages all infrastructure connections (PDO, Redis, Kafka, RabbitMQ) with typed access.
 * Supports two modes per connection:
 * 1. External injection: Register pre-existing connections directly
 * 2. Lazy factories: Register closures that create connections on first access
 *
 * Replaces SharedResource and ResourceKey with a simpler, more explicit API.
 *
 * Usage:
 * ```php
 * // External (DI from outside)
 * $connections = new ConnectionProvider();
 * $connections->addPdo('writer', $existingPdo);
 * $connections->addRedis('cache', $existingRedis);
 *
 * // ENV-based (lazy creation)
 * $connections->configureFromEnv($env);
 *
 * // Mix (external takes priority)
 * $connections->addPdo('writer', $legacyPdo);
 * $connections->configureFromEnv($env); // won't override 'pdo.writer'
 *
 * // Access
 * $pdo = $connections->pdo('writer');    // ?PDO
 * $redis = $connections->redis('cache'); // ?Redis
 * ```
 */
final class ConnectionProvider
{
    /** @var array<string, mixed> Resolved connections */
    private array $connections = [];

    /** @var array<string, Closure(): mixed> Lazy factories */
    private array $factories = [];

    /** @var array<string, mixed> Static cross-domain shared connections */
    private static array $shared = [];

    // =========================================================================
    // Register: External Connections
    // =========================================================================

    public function addPdo(string $name, PDO $pdo): self
    {
        $this->connections["pdo.{$name}"] = $pdo;
        return $this;
    }

    public function addRedis(string $name, Redis $redis): self
    {
        $this->connections["redis.{$name}"] = $redis;
        return $this;
    }

    public function addKafkaProducer(string $name, Producer $producer): self
    {
        $this->connections["kafka.producer.{$name}"] = $producer;
        return $this;
    }

    public function addKafkaConsumer(string $name, KafkaConsumer $consumer): self
    {
        $this->connections["kafka.consumer.{$name}"] = $consumer;
        return $this;
    }

    public function addAmqp(string $name, AMQPConnection $connection): self
    {
        $this->connections["amqp.{$name}"] = $connection;
        return $this;
    }

    // =========================================================================
    // Register: Lazy Factories
    // =========================================================================

    /**
     * Register a lazy factory for a connection.
     *
     * The factory is called once on first access. Result is cached.
     * Does NOT override existing connections or factories.
     *
     * @param string $key Full key (e.g., 'pdo.writer', 'redis.cache')
     * @param Closure(): mixed $factory Factory that creates the connection
     */
    public function addFactory(string $key, Closure $factory): self
    {
        if (!isset($this->connections[$key]) && !isset($this->factories[$key])) {
            $this->factories[$key] = $factory;
        }

        return $this;
    }

    // =========================================================================
    // Typed Getters
    // =========================================================================

    public function pdo(string $name = 'writer'): ?PDO
    {
        $result = $this->resolve("pdo.{$name}");

        if ($result !== null && !$result instanceof PDO) {
            throw new InvalidArgumentException(
                "Connection 'pdo.{$name}' must be PDO instance, got " . get_debug_type($result)
            );
        }

        return $result;
    }

    public function redis(string $name = 'cache'): ?Redis
    {
        $result = $this->resolve("redis.{$name}");

        if ($result !== null && !$result instanceof Redis) {
            throw new InvalidArgumentException(
                "Connection 'redis.{$name}' must be Redis instance, got " . get_debug_type($result)
            );
        }

        return $result;
    }

    public function kafkaProducer(string $name = 'default'): ?Producer
    {
        $result = $this->resolve("kafka.producer.{$name}");

        if ($result !== null && !$result instanceof Producer) {
            throw new InvalidArgumentException(
                "Connection 'kafka.producer.{$name}' must be Producer instance, got " . get_debug_type($result)
            );
        }

        return $result;
    }

    public function kafkaConsumer(string $name = 'default'): ?KafkaConsumer
    {
        $result = $this->resolve("kafka.consumer.{$name}");

        if ($result !== null && !$result instanceof KafkaConsumer) {
            throw new InvalidArgumentException(
                "Connection 'kafka.consumer.{$name}' must be KafkaConsumer instance, got " . get_debug_type($result)
            );
        }

        return $result;
    }

    public function amqp(string $name = 'default'): ?AMQPConnection
    {
        $result = $this->resolve("amqp.{$name}");

        if ($result !== null && !$result instanceof AMQPConnection) {
            throw new InvalidArgumentException(
                "Connection 'amqp.{$name}' must be AMQPConnection instance, got " . get_debug_type($result)
            );
        }

        return $result;
    }

    // =========================================================================
    // Query
    // =========================================================================

    public function hasPdo(string $name = 'writer'): bool
    {
        return $this->has("pdo.{$name}");
    }

    public function hasRedis(string $name = 'cache'): bool
    {
        return $this->has("redis.{$name}");
    }

    public function hasKafkaProducer(string $name = 'default'): bool
    {
        return $this->has("kafka.producer.{$name}");
    }

    public function hasKafkaConsumer(string $name = 'default'): bool
    {
        return $this->has("kafka.consumer.{$name}");
    }

    public function hasAmqp(string $name = 'default'): bool
    {
        return $this->has("amqp.{$name}");
    }

    public function has(string $key): bool
    {
        return isset($this->connections[$key]) || isset($this->factories[$key]);
    }

    // =========================================================================
    // ENV Configuration
    // =========================================================================

    /**
     * Register lazy factories for all connections defined in environment variables.
     *
     * Does NOT override existing connections or factories.
     * Connections are created lazily on first access.
     *
     * @param array<string, mixed> $env Environment variables
     */
    public function configureFromEnv(array $env): self
    {
        $this->configureDbFromEnv($env);
        $this->configureRedisFromEnv($env);
        $this->configureKafkaFromEnv($env);
        $this->configureAmqpFromEnv($env);

        return $this;
    }

    // =========================================================================
    // Cross-Domain Sharing (replaces SharedResource)
    // =========================================================================

    /**
     * Share all resolved connections to static storage for cross-domain reuse.
     *
     * First-write-wins: Existing shared connections are not overwritten.
     */
    public function shareAll(): void
    {
        foreach ($this->connections as $key => $value) {
            self::$shared[$key] ??= $value;
        }
    }

    /**
     * Merge shared connections into this provider.
     *
     * Existing connections take priority over shared ones.
     */
    public function mergeFromShared(): self
    {
        foreach (self::$shared as $key => $value) {
            $this->connections[$key] ??= $value;
        }

        return $this;
    }

    /**
     * Reset shared connections (for testing only).
     *
     * @internal
     */
    public static function resetShared(): void
    {
        self::$shared = [];
    }

    // =========================================================================
    // Internal: Resolution
    // =========================================================================

    private function resolve(string $key): mixed
    {
        if (isset($this->connections[$key])) {
            return $this->connections[$key];
        }

        if (isset($this->factories[$key])) {
            $factory = $this->factories[$key];
            unset($this->factories[$key]);

            $result = $factory();
            if ($result !== null) {
                $this->connections[$key] = $result;
            }

            return $result;
        }

        return null;
    }

    // =========================================================================
    // Internal: ENV-based Factory Registration
    // =========================================================================

    /**
     * @param array<string, mixed> $env
     */
    private function configureDbFromEnv(array $env): void
    {
        // Writer
        if ($env['DB_WRITER_ENABLED'] ?? false) {
            $this->addFactory('pdo.writer', fn(): PDO => $this->createPdoFromEnv($env, 'DB_WRITER'));
        }

        // Readers (1..N)
        for ($i = 1; ($env["DB_READER{$i}_ENABLED"] ?? false); $i++) {
            $index = $i;
            $this->addFactory(
                "pdo.reader{$index}",
                fn(): PDO => $this->createPdoFromEnv($env, "DB_READER{$index}")
            );
        }
    }

    /**
     * @param array<string, mixed> $env
     */
    private function configureRedisFromEnv(array $env): void
    {
        // Cache Redis
        if ($env['CACHE_REDIS_ENABLED'] ?? false) {
            $this->addFactory('redis.cache', fn(): Redis => $this->createRedisFromEnv($env, 'CACHE_REDIS'));
        }

        // Messaging Redis
        if ($env['MESSAGING_REDIS_ENABLED'] ?? false) {
            $this->addFactory('redis.messaging', fn(): Redis => $this->createRedisFromEnv($env, 'MESSAGING_REDIS'));
        }
    }

    /**
     * @param array<string, mixed> $env
     */
    private function configureKafkaFromEnv(array $env): void
    {
        if (!($env['MESSAGING_KAFKA_ENABLED'] ?? false)) {
            return;
        }

        $brokers = $env['MESSAGING_KAFKA_BROKERS'] ?? null;
        if (!is_string($brokers) || trim($brokers) === '') {
            return;
        }

        $this->addFactory('kafka.producer.default', function () use ($env, $brokers): Producer {
            $conf = new Conf();
            $conf->set('metadata.broker.list', $brokers);

            $username = $env['MESSAGING_KAFKA_USERNAME'] ?? null;
            $password = $env['MESSAGING_KAFKA_PASSWORD'] ?? null;

            if (is_string($username) && is_string($password) && $username !== '' && $password !== '') {
                $conf->set('security.protocol', 'SASL_SSL');
                $conf->set('sasl.mechanism', 'PLAIN');
                $conf->set('sasl.username', $username);
                $conf->set('sasl.password', $password);
            }

            return new Producer($conf);
        });
    }

    /**
     * @param array<string, mixed> $env
     */
    private function configureAmqpFromEnv(array $env): void
    {
        if (!($env['MESSAGING_RABBITMQ_ENABLED'] ?? false)) {
            return;
        }

        $host = $env['MESSAGING_RABBITMQ_HOST'] ?? null;
        if (!is_string($host) || trim($host) === '') {
            return;
        }

        $this->addFactory('amqp.default', function () use ($env, $host): AMQPConnection {
            $connection = new AMQPConnection([
                'host' => $host,
                'port' => (int) ($env['MESSAGING_RABBITMQ_PORT'] ?? 5672),
                'login' => $env['MESSAGING_RABBITMQ_USERNAME'] ?? 'guest',
                'password' => $env['MESSAGING_RABBITMQ_PASSWORD'] ?? 'guest',
                'vhost' => '/',
            ]);

            $connection->connect();

            return $connection;
        });
    }

    /**
     * Create PDO connection from environment variables.
     *
     * @param array<string, mixed> $env
     * @throws InvalidArgumentException If required ENV variables are missing
     */
    private function createPdoFromEnv(array $env, string $prefix): PDO
    {
        $driver = strtolower((string) ($env["{$prefix}_DRIVER"] ?? 'mysql'));
        $host = $env["{$prefix}_HOST"] ?? null;
        $user = (string) ($env["{$prefix}_USER"] ?? '');
        $password = (string) ($env["{$prefix}_PASSWORD"] ?? '');
        $database = (string) ($env["{$prefix}_DATABASE"] ?? '');

        $dsn = match ($driver) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $host ?? 'localhost',
                (int) ($env["{$prefix}_PORT"] ?? 3306),
                $database,
                $env["{$prefix}_CHARSET"] ?? 'utf8mb4'
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $host ?? 'localhost',
                (int) ($env["{$prefix}_PORT"] ?? 5432),
                $database
            ),
            'sqlite' => 'sqlite:' . ($env["{$prefix}_PATH"] ?? ':memory:'),
            default => throw new InvalidArgumentException("Unsupported database driver: {$driver}"),
        };

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($env["{$prefix}_PERSISTENT"] ?? false) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        return new PDO($dsn, $user, $password, $options);
    }

    /**
     * Create Redis connection from environment variables.
     *
     * @param array<string, mixed> $env
     * @throws InvalidArgumentException If connection fails
     */
    private function createRedisFromEnv(array $env, string $prefix): Redis
    {
        $host = (string) ($env["{$prefix}_HOST"] ?? 'localhost');
        $port = (int) ($env["{$prefix}_PORT"] ?? 6379);

        $redis = new Redis();

        try {
            $redis->connect($host, $port, 2.5);
        } catch (RedisException $e) {
            throw new InvalidArgumentException(
                "Redis connection failed at {$host}:{$port}: {$e->getMessage()}",
                previous: $e
            );
        }

        $password = $env["{$prefix}_PASSWORD"] ?? null;
        if (is_string($password) && $password !== '') {
            $redis->auth($password);
        }

        $database = $env["{$prefix}_DATABASE"] ?? null;
        if ($database !== null && $database !== '' && $database !== '0') {
            $redis->select((int) $database);
        }

        return $redis;
    }
}
