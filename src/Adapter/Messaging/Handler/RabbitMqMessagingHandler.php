<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Messaging\Handler;

use AMQPConnection;
use Exception;
use JardisAdapter\Messaging\Connection\ExternalRabbitMqConnection;
use JardisAdapter\Messaging\Consumer\RabbitMqConsumer;
use JardisAdapter\Messaging\MessageConsumer;
use JardisAdapter\Messaging\MessagePublisher;
use JardisAdapter\Messaging\Publisher\RabbitMqPublisher;
use JardisCore\Foundation\Adapter\ResourceKey;
use JardisPsr\Foundation\DomainKernelInterface;

/**
 * RabbitMQ Messaging Handler (Priority 2)
 *
 * Responsibility: Configure RabbitMQ messaging for both Publisher and Consumer.
 * Supports two modes:
 * 1. External RabbitMQ: Reuses existing AMQPConnection from ResourceRegistry (connection.amqp)
 * 2. ENV-based: Creates new RabbitMQ connection from environment variables
 *
 * Used for reliable message queuing and routing.
 *
 * Environment variables:
 * - MESSAGING_RABBITMQ_ENABLED: Enable RabbitMQ messaging (default: false)
 * - MESSAGING_RABBITMQ_HOST: RabbitMQ host (required for ENV mode)
 * - MESSAGING_RABBITMQ_PORT: RabbitMQ port (default: 5672)
 * - MESSAGING_RABBITMQ_USERNAME: RabbitMQ username (default: guest)
 * - MESSAGING_RABBITMQ_PASSWORD: RabbitMQ password (default: guest)
 * - MESSAGING_RABBITMQ_QUEUE: Queue name for consumer (default: 'jardis-queue')
 * - MESSAGING_RABBITMQ_EXCHANGE: Exchange name (default: 'amq.direct')
 */
class RabbitMqMessagingHandler
{
    /**
     * Configure RabbitMQ messaging for publisher and consumer.
     *
     * @throws Exception If external AMQP resource is invalid type
     */
    public function __invoke(
        DomainKernelInterface $kernel,
        MessagePublisher $publisher,
        MessageConsumer $consumer
    ): void {
        $resources = $kernel->getResources();

        // ===== External RabbitMQ First =====
        if ($resources->has(ResourceKey::AMQP->value)) {
            $this->configureExternalRabbitMq($kernel, $publisher, $consumer, $resources);
            return;
        }

        // ===== ENV-based RabbitMQ Fallback =====
        if ($kernel->getEnv('MESSAGING_RABBITMQ_ENABLED')) {
            $this->configureEnvRabbitMq($kernel, $publisher, $consumer);
        }
    }

    /**
     * Configure RabbitMQ using external connection from ResourceRegistry.
     *
     * @param \JardisPsr\Foundation\ResourceRegistryInterface $resources
     * @throws Exception If external AMQP is not an AMQPConnection instance
     */
    private function configureExternalRabbitMq(
        DomainKernelInterface $kernel,
        MessagePublisher $publisher,
        MessageConsumer $consumer,
        $resources
    ): void {
        $externalAmqp = $resources->get(ResourceKey::AMQP->value);

        if (!$externalAmqp instanceof AMQPConnection) {
            throw new Exception(
                'Resource "connection.amqp" must be AMQPConnection instance, got ' .
                get_debug_type($externalAmqp)
            );
        }

        $exchangeName = $kernel->getEnv('MESSAGING_RABBITMQ_EXCHANGE') ?? 'amq.direct';
        $exchangeType = AMQP_EX_TYPE_DIRECT;
        $queueName = (string) ($kernel->getEnv('MESSAGING_RABBITMQ_QUEUE') ?? 'jardis-queue');

        $connection = new ExternalRabbitMqConnection($externalAmqp, $exchangeName, $exchangeType);

        // Configure Publisher
        $rabbitPublisher = new RabbitMqPublisher($connection);
        $publisher->addPublisher($rabbitPublisher, 'rabbitmq-external', 2);

        // Configure Consumer
        $rabbitConsumer = new RabbitMqConsumer($connection, $queueName);
        $consumer->addConsumer($rabbitConsumer, 'rabbitmq-external', 2);
    }

    /**
     * Configure RabbitMQ using environment variables.
     */
    private function configureEnvRabbitMq(
        DomainKernelInterface $kernel,
        MessagePublisher $publisher,
        MessageConsumer $consumer
    ): void {
        $host = $kernel->getEnv('MESSAGING_RABBITMQ_HOST');
        if (!$host) {
            return; // No host configured
        }

        $port = (int) ($kernel->getEnv('MESSAGING_RABBITMQ_PORT') ?? 5672);
        $username = $kernel->getEnv('MESSAGING_RABBITMQ_USERNAME') ?? 'guest';
        $password = $kernel->getEnv('MESSAGING_RABBITMQ_PASSWORD') ?? 'guest';
        $queueName = $kernel->getEnv('MESSAGING_RABBITMQ_QUEUE') ?? 'jardis-queue';

        // Configure Publisher
        $publisher->setRabbitMq(
            host: $host,
            port: $port,
            username: $username,
            password: $password,
            options: [],
            priority: 2
        );

        // Configure Consumer
        $consumer->setRabbitMq(
            host: $host,
            queueName: $queueName,
            port: $port,
            username: $username,
            password: $password,
            options: [],
            priority: 2
        );
    }
}
