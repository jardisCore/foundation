<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use AMQPConnection;
use AMQPException;
use Exception;
use InvalidArgumentException;
use JardisCore\Foundation\Adapter\ResourceKey;
use JardisCore\Foundation\Adapter\SharedResource;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogRabbitMq;
use JardisPsr\Foundation\DomainKernelInterface;

/**
 * RabbitMQ Log Handler
 *
 * Creates RabbitMQ log handler from configuration.
 *
 * Connection resolution (fallback chain):
 * 1. AMQP_LOGGER - Dedicated logger AMQP connection
 * 2. AMQP - Reuse messaging AMQP connection
 * 3. ENV-based - Creates new AMQP connection (registered as AMQP_LOGGER)
 *
 * Required options: 'exchange'
 * Optional options: 'host', 'port', 'username', 'password'
 *
 * Example ENV configuration:
 * LOG_HANDLER3_TYPE=rabbitmq
 * LOG_HANDLER3_HOST=rabbitmq-logs.example.com
 * LOG_HANDLER3_PORT=5672
 * LOG_HANDLER3_USERNAME=log_user
 * LOG_HANDLER3_PASSWORD=log_password
 * LOG_HANDLER3_EXCHANGE=logs
 */
class RabbitMqLogHandler
{
    public function __invoke(LoggerHandlerConfig $config, DomainKernelInterface $kernel): LogCommandInterface
    {
        // Get exchange from config
        $exchange = $config->getOption('exchange', 'logs');

        if (!is_string($exchange) || trim($exchange) === '') {
            throw new InvalidArgumentException('RabbitMQ exchange must be a non-empty string');
        }

        // ===== Fallback Chain: AMQP_LOGGER → AMQP → ENV =====
        $connection = $this->resolveAmqpConnection($kernel);

        if ($connection !== null) {
            return new LogRabbitMq($connection, $exchange);
        }

        // ===== Create new AMQP Connection from ENV =====
        $host = $config->getOption('host', 'localhost');
        $portValue = $config->getOption('port', 5672);
        $port = is_numeric($portValue) ? (int) $portValue : 5672;
        $username = $config->getOption('username', 'guest');
        $password = $config->getOption('password', 'guest');

        if (!is_string($host)) {
            throw new InvalidArgumentException('RabbitMQ host must be a string');
        }

        if (!is_string($username)) {
            throw new InvalidArgumentException('RabbitMQ username must be a string');
        }

        if (!is_string($password)) {
            throw new InvalidArgumentException('RabbitMQ password must be a string');
        }

        $credentials = [
            'host' => $host,
            'port' => $port,
            'login' => $username,
            'password' => $password,
            'vhost' => '/',
        ];

        $connection = new AMQPConnection($credentials);

        try {
            $connection->connect();
        } catch (AMQPException $e) {
            throw new InvalidArgumentException(
                "Failed to connect to RabbitMQ at {$host}:{$port}: {$e->getMessage()}",
                previous: $e
            );
        }

        // Register new connection for cross-domain reuse
        SharedResource::setAmqpLogger($connection);

        return new LogRabbitMq($connection, $exchange);
    }

    /**
     * Resolve AMQP connection from fallback chain.
     *
     * @throws Exception If resource exists but is not an AMQPConnection instance
     */
    private function resolveAmqpConnection(DomainKernelInterface $kernel): ?AMQPConnection
    {
        $resources = $kernel->getResources();

        // Fallback chain: AMQP_LOGGER → AMQP
        $keys = [
            ResourceKey::AMQP_LOGGER->value,
            ResourceKey::AMQP->value,
        ];

        foreach ($keys as $key) {
            if ($resources->has($key)) {
                $connection = $resources->get($key);

                if (!$connection instanceof AMQPConnection) {
                    throw new Exception(
                        "Resource \"{$key}\" must be AMQPConnection instance, got " . get_debug_type($connection)
                    );
                }

                return $connection;
            }
        }

        return null;
    }
}
