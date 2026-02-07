<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Messaging;

use Exception;
use JardisCore\Foundation\Adapter\Messaging\Handler\KafkaMessagingHandler;
use JardisCore\Foundation\Adapter\Messaging\Handler\RabbitMqMessagingHandler;
use JardisCore\Foundation\Adapter\Messaging\Handler\RedisMessagingHandler;
use JardisAdapter\Messaging\MessageConsumer;
use JardisAdapter\Messaging\MessagePublisher;
use JardisAdapter\Messaging\MessagingService;
use JardisPsr\Foundation\DomainKernelInterface;

/**
 * Messaging Initialization
 *
 * Responsibility: Orchestrate messaging handler registration and build MessagingService.
 *
 * Determines which handlers to instantiate based on configuration:
 * - Checks for external connections in ResourceRegistry
 * - Checks for ENV configuration
 * - Only instantiates handlers when actually configured (Lazy Loading)
 *
 * Delegates configuration logic to individual handlers:
 * - RedisMessagingHandler: Configures Redis (Priority 0)
 * - KafkaMessagingHandler: Configures Kafka (Priority 1)
 * - RabbitMqMessagingHandler: Configures RabbitMQ (Priority 2)
 * - InMemory: Synchronous in-process messaging (Priority configurable, default 99)
 *
 * Multiple brokers can be enabled for automatic failover.
 */
class InitMessaging
{
    /**
     * Initialize messaging service from handlers.
     *
     * Returns null if no messaging is configured at all.
     * Only instantiates handlers that are actually configured (lazy loading).
     *
     * @throws Exception
     */
    public function __invoke(DomainKernelInterface $kernel): ?MessagingService
    {
        // Early exit: No messaging configured = no objects created
        if (!$this->isMessagingConfigured($kernel)) {
            return null;
        }

        $publisher = new MessagePublisher();
        $consumer = new MessageConsumer();

        // Lazy: Only instantiate handlers that are configured
        if ($this->isRedisConfigured($kernel)) {
            (new RedisMessagingHandler())($kernel, $publisher, $consumer);
        }

        if ($this->isKafkaConfigured($kernel)) {
            (new KafkaMessagingHandler())($kernel, $publisher, $consumer);
        }

        if ($this->isRabbitMqConfigured($kernel)) {
            (new RabbitMqMessagingHandler())($kernel, $publisher, $consumer);
        }

        // InMemory: Simple inline configuration (no external connection needed)
        if ($this->isInMemoryConfigured($kernel)) {
            $priority = (int) ($kernel->getEnv('MESSAGING_INMEMORY_PRIORITY') ?? 99);
            $publisher->setInMemory($priority);
            $consumer->setInMemory($priority);
        }

        return new MessagingService(
            publisherFactory: fn() => $publisher,
            consumerFactory: fn() => $consumer
        );
    }

    /**
     * Check if any messaging system is configured.
     */
    private function isMessagingConfigured(DomainKernelInterface $kernel): bool
    {
        return $this->isRedisConfigured($kernel)
            || $this->isKafkaConfigured($kernel)
            || $this->isRabbitMqConfigured($kernel)
            || $this->isInMemoryConfigured($kernel);
    }

    /**
     * Check if Redis messaging is configured (External or ENV).
     */
    private function isRedisConfigured(DomainKernelInterface $kernel): bool
    {
        return $kernel->getResources()->has('connection.redis.messaging')
            || $kernel->getEnv('MESSAGING_REDIS_ENABLED');
    }

    /**
     * Check if Kafka messaging is configured (External or ENV).
     */
    private function isKafkaConfigured(DomainKernelInterface $kernel): bool
    {
        return $kernel->getResources()->has('connection.kafka.producer')
            || $kernel->getResources()->has('connection.kafka.consumer')
            || $kernel->getEnv('MESSAGING_KAFKA_ENABLED');
    }

    /**
     * Check if RabbitMQ messaging is configured (External or ENV).
     */
    private function isRabbitMqConfigured(DomainKernelInterface $kernel): bool
    {
        return $kernel->getResources()->has('connection.amqp')
            || $kernel->getEnv('MESSAGING_RABBITMQ_ENABLED');
    }

    /**
     * Check if InMemory messaging is configured (ENV only, no external connection).
     */
    private function isInMemoryConfigured(DomainKernelInterface $kernel): bool
    {
        return (bool) $kernel->getEnv('MESSAGING_INMEMORY_ENABLED');
    }
}
