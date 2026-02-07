<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use Exception;
use InvalidArgumentException;
use JardisCore\Foundation\Adapter\ResourceKey;
use JardisCore\Foundation\Adapter\SharedResource;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogKafkaMq;
use JardisPsr\Foundation\DomainKernelInterface;
use RdKafka\Conf;
use RdKafka\Producer;

/**
 * Kafka Message Queue Log Handler
 *
 * Creates Kafka log handler from configuration.
 *
 * Connection resolution (fallback chain):
 * 1. KAFKA_LOGGER - Dedicated logger Kafka producer
 * 2. KAFKA_PRODUCER - Reuse messaging Kafka producer
 * 3. ENV-based - Creates new Kafka producer (registered as KAFKA_LOGGER)
 *
 * Required options: 'brokers' (only for ENV-based)
 * Optional options: 'topic', 'username', 'password' (for SASL authentication)
 *
 * Example ENV configuration:
 * LOG_HANDLER4_TYPE=kafkamq
 * LOG_HANDLER4_BROKERS=kafka-logs.example.com:9092
 * LOG_HANDLER4_TOPIC=app-logs
 * LOG_HANDLER4_USERNAME=log_user
 * LOG_HANDLER4_PASSWORD=log_password
 */
class KafkaMqLogHandler
{
    public function __invoke(LoggerHandlerConfig $config, DomainKernelInterface $kernel): LogCommandInterface
    {
        // Get configuration
        $topic = $config->getOption('topic', 'logs');

        if (!is_string($topic) || trim($topic) === '') {
            throw new InvalidArgumentException('Kafka topic must be a non-empty string');
        }

        // ===== Fallback Chain: KAFKA_LOGGER → KAFKA_PRODUCER → ENV =====
        $producer = $this->resolveKafkaProducer($kernel);

        if ($producer !== null) {
            return new LogKafkaMq($producer, $topic);
        }

        // ===== Create new Kafka Producer from ENV =====
        $brokers = $config->getOption('brokers');
        $username = $config->getOption('username');
        $password = $config->getOption('password');

        if (!is_string($brokers) || trim($brokers) === '') {
            throw new InvalidArgumentException('Kafka brokers must be a non-empty string');
        }

        $conf = new Conf();
        $conf->set('metadata.broker.list', $brokers);

        // Set SASL authentication if credentials provided
        if (is_string($username) && is_string($password) && $username !== '' && $password !== '') {
            $conf->set('security.protocol', 'SASL_SSL');
            $conf->set('sasl.mechanism', 'PLAIN');
            $conf->set('sasl.username', $username);
            $conf->set('sasl.password', $password);
        }

        try {
            $producer = new Producer($conf);
        } catch (Exception $e) {
            throw new InvalidArgumentException(
                "Failed to create Kafka producer for brokers {$brokers}: {$e->getMessage()}",
                previous: $e
            );
        }

        // Register new producer for cross-domain reuse
        SharedResource::setKafkaLogger($producer);

        return new LogKafkaMq($producer, $topic);
    }

    /**
     * Resolve Kafka producer from fallback chain.
     *
     * @throws Exception If resource exists but is not a Producer instance
     */
    private function resolveKafkaProducer(DomainKernelInterface $kernel): ?Producer
    {
        $resources = $kernel->getResources();

        // Fallback chain: KAFKA_LOGGER → KAFKA_PRODUCER
        $keys = [
            ResourceKey::KAFKA_LOGGER->value,
            ResourceKey::KAFKA_PRODUCER->value,
        ];

        foreach ($keys as $key) {
            if ($resources->has($key)) {
                $producer = $resources->get($key);

                if (!$producer instanceof Producer) {
                    throw new Exception(
                        "Resource \"{$key}\" must be RdKafka\\Producer instance, got " . get_debug_type($producer)
                    );
                }

                return $producer;
            }
        }

        return null;
    }
}
