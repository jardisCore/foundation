<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\Handler\BrowserConsoleLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\ConditionalLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\ConsoleLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\DatabaseLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\EmailLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\ErrorLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\FileLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\FingersCrossedLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\KafkaMqLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\LokiLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\NullLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\RabbitMqLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\RedisLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\RedisMqLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\SamplingLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\SlackLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\StashLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\SyslogLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\TeamsLogHandler;
use JardisCore\Foundation\Adapter\Logger\Handler\WebhookLogHandler;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisPsr\Foundation\DomainKernelInterface;

/**
 * Factory for creating log handler instances from configuration.
 *
 * Delegates to individual Handler wrapper classes based on handler type.
 * This maintains the established architectural pattern where each handler
 * type has its own wrapper class.
 *
 * Supports all handler types:
 * - file: File-based logging
 * - slack: Slack webhook integration
 * - loki: Grafana Loki integration
 * - database: Database table logging
 * - teams: Microsoft Teams webhook
 * - redis: Redis storage
 * - rabbitmq: RabbitMQ message queue
 * - kafka: Kafka integration
 * - syslog: System log
 * - errorlog: PHP error_log()
 * - console: Console/STDOUT output
 * - webhook: Custom webhook
 * - email: Email notifications
 * - stash: Logstash integration
 * - null: Discard logs (testing)
 *
 * Wrapper Handlers (require 'wraps' parameter):
 * - sampling: Samples a percentage of log messages (wraps another handler)
 * - fingerscrossed: Buffers logs until activation level is reached (wraps another handler)
 * - conditional: Routes logs based on conditions (wraps multiple handlers)
 */
class LoggerHandlerFactory
{
    /** @var array<int, LoggerHandlerConfig>|null Cached handler configurations */
    private ?array $allConfigs = null;
    /**
     * Create log handler from configuration.
     *
     * Delegates to appropriate Handler wrapper class based on type.
     * Wrapper handlers (sampling, fingerscrossed, conditional) are created with their wrapped handlers.
     *
     * @param LoggerHandlerConfig $config Handler configuration
     * @param DomainKernelInterface $kernel Domain kernel for accessing services
     * @param array<int, LoggerHandlerConfig>|null $allConfigs All handler configurations (for resolving wraps)
     * @return LogCommandInterface Created handler instance
     * @throws InvalidArgumentException If handler type is unknown or wraps configuration is invalid
     */
    public function create(
        LoggerHandlerConfig $config,
        DomainKernelInterface $kernel,
        ?array $allConfigs = null
    ): LogCommandInterface {
        // Cache all configs for wrapper handler resolution
        if ($allConfigs !== null) {
            $this->allConfigs = $allConfigs;
        }

        // Handle wrapper types that need wrapped handlers
        if (in_array($config->type, ['sampling', 'fingerscrossed', 'conditional'], true)) {
            return $this->createWrapperHandler($config, $kernel);
        }

        // Create regular handler
        $handler = match ($config->type) {
            'file' => (new FileLogHandler())($config),
            'slack' => (new SlackLogHandler())($config),
            'loki' => (new LokiLogHandler())($config),
            'database' => (new DatabaseLogHandler())($config, $kernel),
            'teams' => (new TeamsLogHandler())($config),
            'redis' => (new RedisLogHandler())($config, $kernel),
            'redismq' => (new RedisMqLogHandler())($config, $kernel),
            'rabbitmq' => (new RabbitMqLogHandler())($config, $kernel),
            'kafka' => (new KafkaMqLogHandler())($config, $kernel),
            'syslog' => (new SyslogLogHandler())($config),
            'errorlog' => (new ErrorLogHandler())($config),
            'console' => (new ConsoleLogHandler())($config),
            'browserconsole' => (new BrowserConsoleLogHandler())($config),
            'webhook' => (new WebhookLogHandler())($config),
            'email' => (new EmailLogHandler())($config),
            'stash' => (new StashLogHandler())($config),
            'null' => (new NullLogHandler())($config),
            default => throw new InvalidArgumentException(
                "Unsupported log handler type: '{$config->type}'. " .
                "Supported types: file, slack, loki, database, teams, redis, redismq, rabbitmq, kafka, " .
                "syslog, errorlog, console, browserconsole, webhook, email, stash, null, sampling, " .
                "fingerscrossed, conditional"
            ),
        };

        // Set handler name centrally if provided
        if ($config->name !== null) {
            $handler->setHandlerName($config->name);
        }

        return $handler;
    }

    /**
     * Create a wrapper handler (sampling, fingerscrossed, conditional) with wrapped handlers.
     *
     * @param LoggerHandlerConfig $config Wrapper handler configuration
     * @param DomainKernelInterface $kernel Domain kernel for accessing services
     * @return LogCommandInterface Created wrapper handler instance
     * @throws InvalidArgumentException If wraps parameter is missing or invalid
     */
    private function createWrapperHandler(
        LoggerHandlerConfig $config,
        DomainKernelInterface $kernel
    ): LogCommandInterface {
        // Get 'wraps' parameter
        $wraps = $config->getOption('wraps');

        if ($wraps === null || (is_string($wraps) && trim($wraps) === '')) {
            throw new InvalidArgumentException(
                "Wrapper handler '{$config->type}' requires 'wraps' parameter to specify which handler(s) to wrap. " .
                "Example: LOG_HANDLER{N}_WRAPS=handler_name"
            );
        }

        // Parse wraps (can be string or array, comma-separated)
        $wrapNames = is_array($wraps) ? $wraps : array_map('trim', explode(',', (string) $wraps));

        // Resolve wrapped handlers
        $wrappedHandlers = [];
        foreach ($wrapNames as $wrapName) {
            if ($wrapName === '') {
                continue;
            }

            $wrappedConfig = $this->findConfigByName($wrapName);

            if ($wrappedConfig === null) {
                throw new InvalidArgumentException(
                    "Wrapper handler '{$config->type}' (name: '{$config->name}') " .
                    "references unknown handler '{$wrapName}'. " .
                    "Make sure the wrapped handler is defined with LOG_HANDLER{N}_NAME={$wrapName}"
                );
            }

            // Recursively create wrapped handler (supports nested wrapping)
            $handler = $this->create($wrappedConfig, $kernel, $this->allConfigs);

            // Ensure handler is streamable for wrapper handlers
            if (!$handler instanceof StreamableLogCommandInterface) {
                throw new InvalidArgumentException(
                    "Wrapper handler '{$config->type}' (name: '{$config->name}') " .
                    "requires wrapped handler '{$wrapName}' to implement StreamableLogCommandInterface"
                );
            }

            $wrappedHandlers[] = $handler;
        }

        if (empty($wrappedHandlers)) {
            throw new InvalidArgumentException(
                "Wrapper handler '{$config->type}' has no valid wrapped handlers"
            );
        }

        // Create wrapper handler with wrapped handler(s)
        // PHPStan now knows $wrappedHandlers contains StreamableLogCommandInterface instances
        $handler = match ($config->type) {
            'sampling' => (new SamplingLogHandler())($config, $wrappedHandlers[0]),
            'fingerscrossed' => (new FingersCrossedLogHandler())($config, $wrappedHandlers[0]),
            'conditional' => (new ConditionalLogHandler())($config, $kernel, $wrappedHandlers),
            default => throw new InvalidArgumentException("Unknown wrapper type: {$config->type}"),
        };

        // Set handler name centrally if provided
        if ($config->name !== null) {
            $handler->setHandlerName($config->name);
        }

        return $handler;
    }

    /**
     * Find a handler configuration by its name.
     *
     * @param string $name Handler name to search for
     * @return LoggerHandlerConfig|null Found configuration or null
     */
    private function findConfigByName(string $name): ?LoggerHandlerConfig
    {
        if ($this->allConfigs === null) {
            return null;
        }

        foreach ($this->allConfigs as $config) {
            if ($config->name === $name) {
                return $config;
            }
        }

        return null;
    }
}
