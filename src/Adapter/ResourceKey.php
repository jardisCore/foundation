<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter;

/**
 * Enum defining type-safe resource keys for external connections.
 *
 * Provides compile-time safety and IDE autocompletion for resource registry keys,
 * eliminating the risk of typos in free-string resource identifiers.
 */
enum ResourceKey: string
{
    /**
     * Redis connection for caching layer.
     */
    case REDIS_CACHE = 'connection.redis.cache';

    /**
     * Redis connection for messaging (publish/subscribe).
     */
    case REDIS_MESSAGING = 'connection.redis.messaging';

    /**
     * PDO connection for database writer.
     */
    case PDO_WRITER = 'connection.pdo.writer';

    /**
     * PDO connection for cache database layer.
     */
    case PDO_CACHE = 'connection.pdo.cache';

    /**
     * Kafka producer connection.
     */
    case KAFKA_PRODUCER = 'connection.kafka.producer';

    /**
     * Kafka consumer connection.
     */
    case KAFKA_CONSUMER = 'connection.kafka.consumer';

    /**
     * RabbitMQ AMQP connection.
     */
    case AMQP = 'connection.amqp';

    /**
     * Redis connection for logging.
     */
    case REDIS_LOGGER = 'connection.redis.logger';

    /**
     * Kafka producer connection for logging.
     */
    case KAFKA_LOGGER = 'connection.kafka.logger';

    /**
     * RabbitMQ AMQP connection for logging.
     */
    case AMQP_LOGGER = 'connection.amqp.logger';

    /**
     * DI Container service.
     */
    case CONTAINER = 'service.container';

    /**
     * Generate a PDO reader resource key with the given index.
     *
     * @param int $index Reader index (e.g., 1 for 'connection.pdo.reader1')
     * @return string Resource key for the specific reader
     */
    public static function pdoReader(int $index): string
    {
        return 'connection.pdo.reader' . $index;
    }
}
