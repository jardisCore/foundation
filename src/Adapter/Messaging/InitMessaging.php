<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Messaging;

use JardisAdapter\Messaging\Connection\ExternalKafkaConsumerConnection;
use JardisAdapter\Messaging\Connection\ExternalKafkaProducerConnection;
use JardisAdapter\Messaging\Connection\ExternalRabbitMqConnection;
use JardisAdapter\Messaging\Connection\ExternalRedisConnection;
use JardisAdapter\Messaging\Consumer\InMemoryConsumer;
use JardisAdapter\Messaging\Consumer\KafkaConsumer;
use JardisAdapter\Messaging\Consumer\RabbitMqConsumer;
use JardisAdapter\Messaging\Consumer\RedisConsumer;
use JardisAdapter\Messaging\MessageConsumer;
use JardisAdapter\Messaging\MessagePublisher;
use JardisAdapter\Messaging\MessagingService;
use JardisAdapter\Messaging\Publisher\InMemoryPublisher;
use JardisAdapter\Messaging\Publisher\KafkaPublisher;
use JardisAdapter\Messaging\Publisher\RabbitMqPublisher;
use JardisAdapter\Messaging\Publisher\RedisPublisher;
use JardisAdapter\Messaging\Transport\InMemoryTransport;
use JardisCore\Foundation\Adapter\ConnectionProvider;
use JardisPort\Messaging\MessagingServiceInterface;

/**
 * Initialize Messaging Service
 *
 * Assembles MessagingService from pre-resolved connections.
 * No ENV reading, no connection creation — pure assembly.
 *
 * Supports Redis, Kafka, RabbitMQ and InMemory transports.
 */
class InitMessaging
{
    /**
     * @param array<string, mixed> $config Messaging configuration from ENV
     */
    public function __invoke(ConnectionProvider $connections, array $config): ?MessagingServiceInterface
    {
        $publisher = new MessagePublisher();
        $consumer = new MessageConsumer();
        $hasAny = false;

        // Redis (Priority 0)
        if ($connections->hasRedis('messaging')) {
            $redis = $connections->redis('messaging');
            if ($redis !== null) {
                $useStreams = (bool) ($config['redis_use_streams'] ?? false);
                $redisConn = new ExternalRedisConnection($redis);

                $publisher->addPublisher(new RedisPublisher($redisConn, $useStreams), 'redis', 0);
                $consumer->addConsumer(new RedisConsumer($redisConn, $useStreams), 'redis', 0);
                $hasAny = true;
            }
        }

        // Kafka Producer (Priority 1)
        if ($connections->hasKafkaProducer()) {
            $kafkaProducer = $connections->kafkaProducer();
            if ($kafkaProducer !== null) {
                $kafkaConn = new ExternalKafkaProducerConnection($kafkaProducer);
                $publisher->addPublisher(new KafkaPublisher($kafkaConn), 'kafka', 1);
                $hasAny = true;
            }
        }

        // Kafka Consumer (Priority 1)
        if ($connections->hasKafkaConsumer()) {
            $kafkaConsumer = $connections->kafkaConsumer();
            if ($kafkaConsumer !== null) {
                $kafkaConsumerConn = new ExternalKafkaConsumerConnection($kafkaConsumer);
                $consumer->addConsumer(new KafkaConsumer($kafkaConsumerConn), 'kafka', 1);
            }
        }

        // RabbitMQ (Priority 2)
        if ($connections->hasAmqp()) {
            $amqpConn = $connections->amqp();
            if ($amqpConn !== null) {
                $exchangeName = (string) ($config['rabbitmq_exchange'] ?? 'amq.direct');
                $exchangeType = (string) ($config['rabbitmq_exchange_type'] ?? AMQP_EX_TYPE_DIRECT);
                $queueName = (string) ($config['rabbitmq_queue'] ?? 'jardis-queue');

                $rabbitConn = new ExternalRabbitMqConnection($amqpConn, $exchangeName, $exchangeType);

                $publisher->addPublisher(new RabbitMqPublisher($rabbitConn), 'rabbitmq', 2);
                $consumer->addConsumer(new RabbitMqConsumer($rabbitConn, $queueName), 'rabbitmq', 2);
                $hasAny = true;
            }
        }

        // InMemory (Priority configurable, default 99)
        if ($config['inmemory_enabled'] ?? false) {
            $priority = (int) ($config['inmemory_priority'] ?? 99);
            $transport = InMemoryTransport::getInstance();
            $publisher->addPublisher(new InMemoryPublisher($transport), 'inmemory', $priority);
            $consumer->addConsumer(new InMemoryConsumer($transport), 'inmemory', $priority);
            $hasAny = true;
        }

        if (!$hasAny) {
            return null;
        }

        return new MessagingService(
            publisherFactory: static fn() => $publisher,
            consumerFactory: static fn() => $consumer
        );
    }
}
