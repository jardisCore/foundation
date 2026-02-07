<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Messaging\Handler;

use Exception;
use JardisAdapter\Messaging\Connection\ExternalKafkaConnection;
use JardisAdapter\Messaging\Consumer\KafkaConsumer;
use JardisAdapter\Messaging\MessageConsumer;
use JardisAdapter\Messaging\MessagePublisher;
use JardisAdapter\Messaging\Publisher\KafkaPublisher;
use JardisCore\Foundation\Adapter\ResourceKey;
use JardisPsr\Foundation\DomainKernelInterface;
use RdKafka\KafkaConsumer as RdKafkaConsumer;
use RdKafka\Producer;

/**
 * Kafka Messaging Handler (Priority 1)
 *
 * Responsibility: Configure Kafka messaging for both Publisher and Consumer.
 * Supports two modes:
 * 1. External Kafka: Reuses existing Producer/Consumer instances from ResourceRegistry
 *    - connection.kafka.producer
 *    - connection.kafka.consumer
 * 2. ENV-based: Creates new Kafka connections from environment variables
 *
 * Used for high-throughput distributed messaging.
 *
 * Environment variables:
 * - MESSAGING_KAFKA_ENABLED: Enable Kafka messaging (default: false)
 * - MESSAGING_KAFKA_BROKERS: Comma-separated list of Kafka brokers (required for ENV mode)
 * - MESSAGING_KAFKA_GROUP_ID: Consumer group ID (default: 'jardis-consumer-group')
 * - MESSAGING_KAFKA_USERNAME: SASL username (optional)
 * - MESSAGING_KAFKA_PASSWORD: SASL password (optional)
 */
class KafkaMessagingHandler
{
    /**
     * Configure Kafka messaging for publisher and consumer.
     *
     * @throws Exception If external Kafka resources are invalid types
     */
    public function __invoke(
        DomainKernelInterface $kernel,
        MessagePublisher $publisher,
        MessageConsumer $consumer
    ): void {
        $resources = $kernel->getResources();

        // ===== External Kafka First =====
        $hasExternalProducer = $resources->has(ResourceKey::KAFKA_PRODUCER->value);
        $hasExternalConsumer = $resources->has(ResourceKey::KAFKA_CONSUMER->value);

        if ($hasExternalProducer || $hasExternalConsumer) {
            $this->configureExternalKafka($kernel, $publisher, $consumer, $resources);
            return;
        }

        // ===== ENV-based Kafka Fallback =====
        if ($kernel->getEnv('MESSAGING_KAFKA_ENABLED')) {
            $this->configureEnvKafka($kernel, $publisher, $consumer);
        }
    }

    /**
     * Configure Kafka using external connections from ResourceRegistry.
     *
     * @param \JardisPsr\Foundation\ResourceRegistryInterface $resources
     * @throws Exception If external Kafka resources are not correct types
     */
    private function configureExternalKafka(
        DomainKernelInterface $kernel,
        MessagePublisher $publisher,
        MessageConsumer $consumer,
        $resources
    ): void {
        // Configure Producer if available
        if ($resources->has(ResourceKey::KAFKA_PRODUCER->value)) {
            $externalProducer = $resources->get(ResourceKey::KAFKA_PRODUCER->value);

            if (!$externalProducer instanceof Producer) {
                throw new Exception(
                    'Resource "connection.kafka.producer" must be RdKafka\Producer instance, got ' .
                    get_debug_type($externalProducer)
                );
            }

            $connection = new ExternalKafkaConnection($externalProducer);
            $kafkaPublisher = new KafkaPublisher($connection);
            $publisher->addPublisher($kafkaPublisher, 'kafka-external', 1);
        }

        // Configure Consumer if available
        if ($resources->has(ResourceKey::KAFKA_CONSUMER->value)) {
            $externalConsumer = $resources->get(ResourceKey::KAFKA_CONSUMER->value);

            if (!$externalConsumer instanceof RdKafkaConsumer) {
                throw new Exception(
                    'Resource "connection.kafka.consumer" must be RdKafka\KafkaConsumer instance, got ' .
                    get_debug_type($externalConsumer)
                );
            }

            $groupId = (string) ($kernel->getEnv('MESSAGING_KAFKA_GROUP_ID') ?? 'jardis-consumer-group');
            $connection = new ExternalKafkaConnection($externalConsumer);
            $kafkaConsumer = new KafkaConsumer($connection, $groupId);
            $consumer->addConsumer($kafkaConsumer, 'kafka-external', 1);
        }
    }

    /**
     * Configure Kafka using environment variables.
     */
    private function configureEnvKafka(
        DomainKernelInterface $kernel,
        MessagePublisher $publisher,
        MessageConsumer $consumer
    ): void {
        $brokers = $kernel->getEnv('MESSAGING_KAFKA_BROKERS');
        if (!$brokers) {
            return; // No brokers configured
        }

        $groupId = $kernel->getEnv('MESSAGING_KAFKA_GROUP_ID') ?? 'jardis-consumer-group';
        $username = $kernel->getEnv('MESSAGING_KAFKA_USERNAME') ?: null;
        $password = $kernel->getEnv('MESSAGING_KAFKA_PASSWORD') ?: null;

        // Configure Publisher
        $publisher->setKafka(
            brokers: $brokers,
            username: $username,
            password: $password,
            options: [],
            priority: 1
        );

        // Configure Consumer
        $consumer->setKafka(
            brokers: $brokers,
            groupId: $groupId,
            username: $username,
            password: $password,
            options: [],
            priority: 1
        );
    }
}
