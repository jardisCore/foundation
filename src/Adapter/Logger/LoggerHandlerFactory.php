<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger;

use AMQPConnection;
use AMQPException;
use InvalidArgumentException;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Handler\LogBrowserConsole;
use JardisAdapter\Logger\Handler\LogConsole;
use JardisAdapter\Logger\Handler\LogDatabase;
use JardisAdapter\Logger\Handler\LogEmail;
use JardisAdapter\Logger\Handler\LogErrorLog;
use JardisAdapter\Logger\Handler\LogFile;
use JardisAdapter\Logger\Handler\LogKafkaMq;
use JardisAdapter\Logger\Handler\LogLoki;
use JardisAdapter\Logger\Handler\LogNull;
use JardisAdapter\Logger\Handler\LogRabbitMq;
use JardisAdapter\Logger\Handler\LogRedis;
use JardisAdapter\Logger\Handler\LogRedisMq;
use JardisAdapter\Logger\Handler\LogConditional;
use JardisAdapter\Logger\Handler\LogFingersCrossed;
use JardisAdapter\Logger\Handler\LogSampling;
use JardisAdapter\Logger\Handler\LogSlack;
use JardisAdapter\Logger\Handler\LogStash;
use JardisAdapter\Logger\Handler\LogSyslog;
use JardisAdapter\Logger\Handler\LogTeams;
use JardisAdapter\Logger\Handler\LogWebhook;
use JardisCore\Foundation\Adapter\ConnectionProvider;
use JardisCore\Foundation\Adapter\Logger\Condition\ConditionParser;
use RdKafka\Conf;
use RdKafka\Producer;
use Redis;
use RedisException;
use RuntimeException;

/**
 * Factory for creating log handler instances from configuration.
 *
 * Creates adapter handler instances directly — no wrapper classes.
 * Connection-based handlers get connections from ConnectionProvider.
 */
class LoggerHandlerFactory
{
    /** @var array<int, LoggerHandlerConfig>|null */
    private ?array $allConfigs = null;

    /**
     * Create log handler from configuration.
     *
     * @param array<int, LoggerHandlerConfig>|null $allConfigs All handler configurations (for resolving wraps)
     */
    public function create(
        LoggerHandlerConfig $config,
        ConnectionProvider $connections,
        ?array $allConfigs = null
    ): LogCommandInterface {
        if ($allConfigs !== null) {
            $this->allConfigs = $allConfigs;
        }

        // Wrapper types (need wrapped handlers)
        if (in_array($config->type, ['sampling', 'fingerscrossed', 'conditional'], true)) {
            return $this->createWrapperHandler($config, $connections);
        }

        $handler = match ($config->type) {
            'file' => $this->createFileHandler($config),
            'console' => new LogConsole($config->level),
            'errorlog' => new LogErrorLog($config->level),
            'syslog' => $this->createSyslogHandler($config),
            'null' => new LogNull($config->level),
            'browserconsole' => new LogBrowserConsole($config->level),
            'database' => $this->createDatabaseHandler($config, $connections),
            'redis' => $this->createRedisHandler($config, $connections),
            'redismq' => $this->createRedisMqHandler($config, $connections),
            'kafka' => $this->createKafkaHandler($config, $connections),
            'rabbitmq' => $this->createRabbitMqHandler($config, $connections),
            'slack' => $this->createSlackHandler($config),
            'teams' => $this->createTeamsHandler($config),
            'loki' => $this->createLokiHandler($config),
            'webhook' => $this->createWebhookHandler($config),
            'email' => $this->createEmailHandler($config),
            'stash' => $this->createStashHandler($config),
            default => throw new InvalidArgumentException(
                "Unsupported log handler type: '{$config->type}'. " .
                "Supported: file, console, errorlog, syslog, null, browserconsole, database, " .
                "redis, redismq, kafka, rabbitmq, slack, teams, loki, webhook, email, stash, " .
                "sampling, fingerscrossed, conditional"
            ),
        };

        if ($config->name !== null) {
            $handler->setHandlerName($config->name);
        }

        return $handler;
    }

    // =========================================================================
    // Simple Handlers (no connections)
    // =========================================================================

    private function createFileHandler(LoggerHandlerConfig $config): LogCommandInterface
    {
        $path = $config->getOption('path');
        if (!is_string($path) || trim($path) === '') {
            throw new InvalidArgumentException("File handler requires 'path' option");
        }

        return new LogFile($config->level, $path);
    }

    private function createSyslogHandler(LoggerHandlerConfig $config): LogCommandInterface
    {
        return new LogSyslog($config->level);
    }

    private function createSlackHandler(LoggerHandlerConfig $config): LogCommandInterface
    {
        $webhook = $config->getOption('webhook');
        if (!is_string($webhook) || trim($webhook) === '') {
            throw new InvalidArgumentException("Slack handler requires 'webhook' option");
        }

        $timeout = is_numeric($config->getOption('timeout')) ? (int) $config->getOption('timeout') : 10;
        $retries = is_numeric($config->getOption('retries')) ? (int) $config->getOption('retries') : 3;

        return new LogSlack($config->level, $webhook, $timeout, $retries);
    }

    private function createTeamsHandler(LoggerHandlerConfig $config): LogCommandInterface
    {
        $webhook = $config->getOption('webhook');
        if (!is_string($webhook) || trim($webhook) === '') {
            throw new InvalidArgumentException("Teams handler requires 'webhook' option");
        }

        $timeout = is_numeric($config->getOption('timeout')) ? (int) $config->getOption('timeout') : 10;
        $retries = is_numeric($config->getOption('retries')) ? (int) $config->getOption('retries') : 3;

        return new LogTeams($config->level, $webhook, $timeout, $retries);
    }

    private function createLokiHandler(LoggerHandlerConfig $config): LogCommandInterface
    {
        $url = $config->getOption('url');
        if (!is_string($url) || trim($url) === '') {
            throw new InvalidArgumentException("Loki handler requires 'url' option");
        }

        $timeout = is_numeric($config->getOption('timeout')) ? (int) $config->getOption('timeout') : 10;
        $retries = is_numeric($config->getOption('retries')) ? (int) $config->getOption('retries') : 3;

        /** @var array<string, string> $labels */
        $labels = is_array($config->getOption('labels')) ? $config->getOption('labels') : [];

        return new LogLoki($config->level, $url, $labels, $timeout, $retries);
    }

    private function createWebhookHandler(LoggerHandlerConfig $config): LogCommandInterface
    {
        $url = $config->getOption('url');
        if (!is_string($url) || trim($url) === '') {
            throw new InvalidArgumentException("Webhook handler requires 'url' option");
        }

        $method = is_string($config->getOption('method')) ? $config->getOption('method') : 'POST';
        /** @var array<string, string> $headers */
        $headers = is_array($config->getOption('headers')) ? $config->getOption('headers') : [];
        $timeout = is_numeric($config->getOption('timeout')) ? (int) $config->getOption('timeout') : 10;
        $retries = is_numeric($config->getOption('retries')) ? (int) $config->getOption('retries') : 3;
        $retryDelay = is_numeric($config->getOption('retry_delay')) ? (int) $config->getOption('retry_delay') : 1;

        return new LogWebhook($config->level, $url, $method, $headers, $timeout, $retries, $retryDelay);
    }

    private function createEmailHandler(LoggerHandlerConfig $config): LogCommandInterface
    {
        $toEmail = $config->getOption('to');
        $fromEmail = $config->getOption('from');

        if (!is_string($toEmail) || !is_string($fromEmail)) {
            throw new InvalidArgumentException("Email handler requires 'to' and 'from' options");
        }

        $subject = is_string($config->getOption('subject')) ? $config->getOption('subject') : 'Application Log';
        $smtpHost = is_string($config->getOption('smtp_host')) ? $config->getOption('smtp_host') : 'localhost';
        $smtpPort = is_numeric($config->getOption('smtp_port')) ? (int) $config->getOption('smtp_port') : 1025;

        return new LogEmail($config->level, $toEmail, $fromEmail, $subject, $smtpHost, $smtpPort);
    }

    private function createStashHandler(LoggerHandlerConfig $config): LogCommandInterface
    {
        $host = is_string($config->getOption('host')) ? $config->getOption('host') : 'localhost';
        $port = is_numeric($config->getOption('port')) ? (int) $config->getOption('port') : 5044;

        return new LogStash($config->level, $host, $port);
    }

    // =========================================================================
    // Connection-based Handlers
    // =========================================================================

    private function createDatabaseHandler(
        LoggerHandlerConfig $config,
        ConnectionProvider $connections
    ): LogCommandInterface {
        $pdo = $connections->pdo('writer');
        if ($pdo === null) {
            throw new RuntimeException(
                "Database log handler requires active database connection. " .
                "Ensure DB_WRITER_ENABLED=true or inject a PDO connection."
            );
        }

        $table = is_string($config->getOption('table')) ? $config->getOption('table') : 'logContextData';

        return new LogDatabase($config->level, $pdo, $table);
    }

    private function createRedisHandler(
        LoggerHandlerConfig $config,
        ConnectionProvider $connections
    ): LogCommandInterface {
        $redis = $connections->redis('logger')
            ?? $connections->redis('messaging')
            ?? $connections->redis('cache');

        $channel = $config->getOption('channel');
        $useMessageQueue = is_string($channel) && trim($channel) !== '';

        if ($redis !== null) {
            if ($useMessageQueue) {
                return new LogRedisMq($redis, $channel);
            }

            $fallbackChannel = 'logs:' . strtolower($config->level);
            return new LogRedisMq($redis, $fallbackChannel);
        }

        // Create new Redis from handler-specific config
        $redis = $this->createRedisFromHandlerConfig($config);

        if ($useMessageQueue) {
            return new LogRedisMq($redis, $channel);
        }

        $ttl = is_numeric($config->getOption('ttl')) ? (int) $config->getOption('ttl') : 3600;

        return new LogRedis($config->level, $redis, $ttl);
    }

    private function createRedisMqHandler(
        LoggerHandlerConfig $config,
        ConnectionProvider $connections
    ): LogCommandInterface {
        $channel = $config->getOption('channel');
        if (!is_string($channel) || trim($channel) === '') {
            $channel = 'logs';
        }

        $redis = $connections->redis('logger')
            ?? $connections->redis('messaging')
            ?? $connections->redis('cache');

        if ($redis !== null) {
            return new LogRedisMq($redis, $channel);
        }

        $redis = $this->createRedisFromHandlerConfig($config);
        return new LogRedisMq($redis, $channel);
    }

    private function createKafkaHandler(
        LoggerHandlerConfig $config,
        ConnectionProvider $connections
    ): LogCommandInterface {
        $topic = $config->getOption('topic');
        if (!is_string($topic) || trim($topic) === '') {
            $topic = 'logs';
        }

        $producer = $connections->kafkaProducer('logger') ?? $connections->kafkaProducer();

        if ($producer !== null) {
            return new LogKafkaMq($producer, $topic);
        }

        // Create new Kafka producer from handler-specific config
        $brokers = $config->getOption('brokers');
        if (!is_string($brokers) || trim($brokers) === '') {
            throw new InvalidArgumentException(
                "Kafka log handler requires 'brokers' option or a registered Kafka producer"
            );
        }

        $conf = new Conf();
        $conf->set('metadata.broker.list', $brokers);

        $username = $config->getOption('username');
        $password = $config->getOption('password');

        if (is_string($username) && is_string($password) && $username !== '' && $password !== '') {
            $conf->set('security.protocol', 'SASL_SSL');
            $conf->set('sasl.mechanism', 'PLAIN');
            $conf->set('sasl.username', $username);
            $conf->set('sasl.password', $password);
        }

        return new LogKafkaMq(new Producer($conf), $topic);
    }

    private function createRabbitMqHandler(
        LoggerHandlerConfig $config,
        ConnectionProvider $connections
    ): LogCommandInterface {
        $exchange = $config->getOption('exchange');
        if (!is_string($exchange) || trim($exchange) === '') {
            $exchange = 'logs';
        }

        $amqp = $connections->amqp('logger') ?? $connections->amqp();

        if ($amqp !== null) {
            return new LogRabbitMq($amqp, $exchange);
        }

        // Create new AMQP connection from handler-specific config
        $host = is_string($config->getOption('host')) ? $config->getOption('host') : 'localhost';
        $port = is_numeric($config->getOption('port')) ? (int) $config->getOption('port') : 5672;
        $username = is_string($config->getOption('username')) ? $config->getOption('username') : 'guest';
        $password = is_string($config->getOption('password')) ? $config->getOption('password') : 'guest';

        $connection = new AMQPConnection([
            'host' => $host,
            'port' => $port,
            'login' => $username,
            'password' => $password,
            'vhost' => '/',
        ]);

        try {
            $connection->connect();
        } catch (AMQPException $e) {
            throw new InvalidArgumentException(
                "RabbitMQ connection failed at {$host}:{$port}: {$e->getMessage()}",
                previous: $e
            );
        }

        return new LogRabbitMq($connection, $exchange);
    }

    // =========================================================================
    // Wrapper Handlers
    // =========================================================================

    private function createWrapperHandler(
        LoggerHandlerConfig $config,
        ConnectionProvider $connections
    ): LogCommandInterface {
        $wraps = $config->getOption('wraps');
        if ($wraps === null || (is_string($wraps) && trim($wraps) === '')) {
            throw new InvalidArgumentException(
                "Wrapper handler '{$config->type}' requires 'wraps' parameter"
            );
        }

        $wrapNames = is_array($wraps) ? $wraps : array_map('trim', explode(',', (string) $wraps));

        $wrappedHandlers = [];
        foreach ($wrapNames as $wrapName) {
            if ($wrapName === '' || !is_string($wrapName)) {
                continue;
            }

            $wrappedConfig = $this->findConfigByName($wrapName);
            if ($wrappedConfig === null) {
                throw new InvalidArgumentException(
                    "Wrapper '{$config->type}' references unknown handler '{$wrapName}'"
                );
            }

            $handler = $this->create($wrappedConfig, $connections, $this->allConfigs);
            if (!$handler instanceof StreamableLogCommandInterface) {
                throw new InvalidArgumentException(
                    "Wrapped handler '{$wrapName}' must implement StreamableLogCommandInterface"
                );
            }

            $wrappedHandlers[] = $handler;
        }

        if ($wrappedHandlers === []) {
            throw new InvalidArgumentException(
                "Wrapper handler '{$config->type}' has no valid wrapped handlers"
            );
        }

        $handler = match ($config->type) {
            'sampling' => $this->createSamplingHandler($config, $wrappedHandlers[0]),
            'fingerscrossed' => $this->createFingersCrossedHandler($config, $wrappedHandlers[0]),
            'conditional' => $this->createConditionalHandler($config, $wrappedHandlers),
            default => throw new InvalidArgumentException("Unknown wrapper type: {$config->type}"),
        };

        if ($config->name !== null) {
            $handler->setHandlerName($config->name);
        }

        return $handler;
    }

    private function createSamplingHandler(
        LoggerHandlerConfig $config,
        StreamableLogCommandInterface $wrappedHandler
    ): LogCommandInterface {
        $strategy = is_string($config->getOption('strategy')) ? $config->getOption('strategy') : 'smart';
        $samplingConfig = [];

        $rate = $config->getOption('rate');
        if (is_numeric($rate)) {
            $samplingConfig['rate'] = (float) $rate;
        }

        return new LogSampling($wrappedHandler, $strategy, $samplingConfig);
    }

    private function createFingersCrossedHandler(
        LoggerHandlerConfig $config,
        StreamableLogCommandInterface $wrappedHandler
    ): LogCommandInterface {
        $activationLevel = is_string($config->getOption('activation_level'))
            ? $config->getOption('activation_level')
            : 'error';

        $bufferSize = is_numeric($config->getOption('buffer_size'))
            ? (int) $config->getOption('buffer_size')
            : 100;

        return new LogFingersCrossed($wrappedHandler, $activationLevel, $bufferSize);
    }

    /**
     * @param array<int, StreamableLogCommandInterface> $wrappedHandlers
     */
    private function createConditionalHandler(
        LoggerHandlerConfig $config,
        array $wrappedHandlers
    ): LogCommandInterface {
        $conditionsStr = $config->getOption('conditions');
        $conditionNames = is_string($conditionsStr)
            ? array_map('trim', explode(',', $conditionsStr))
            : [];

        $parser = new ConditionParser();
        $conditionNs = 'JardisCore\\Foundation\\Adapter\\Logger\\Condition\\';
        $conditionLoader = static function (string $name) use ($conditionNs): callable {
            $className = $conditionNs . $name;
            if (!class_exists($className)) {
                // Try PascalCase conversion (e.g., is_cli → IsCli)
                $shortName = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
                $className = $conditionNs . $shortName;
            }

            if (class_exists($className)) {
                $instance = new $className();
                if (!is_callable($instance)) {
                    throw new InvalidArgumentException("Condition {$name} is not callable");
                }
                return $instance;
            }

            throw new InvalidArgumentException("Unknown condition: {$name}");
        };

        $conditionalHandlers = [];
        foreach ($wrappedHandlers as $index => $handler) {
            $conditionName = $conditionNames[$index] ?? 'AlwaysTrue';
            $condition = $parser->parse($conditionName, $conditionLoader);
            $conditionalHandlers[] = [$condition, $handler];
        }

        return new LogConditional($conditionalHandlers);
    }

    // =========================================================================
    // Internal Helpers
    // =========================================================================

    private function createRedisFromHandlerConfig(LoggerHandlerConfig $config): Redis
    {
        $host = is_string($config->getOption('host')) ? $config->getOption('host') : 'localhost';
        $port = is_numeric($config->getOption('port')) ? (int) $config->getOption('port') : 6379;
        $password = $config->getOption('password');
        $database = is_numeric($config->getOption('database')) ? (int) $config->getOption('database') : 0;

        $redis = new Redis();

        try {
            $redis->connect($host, $port, 2.5);
        } catch (RedisException $e) {
            throw new InvalidArgumentException(
                "Redis connection failed at {$host}:{$port}: {$e->getMessage()}",
                previous: $e
            );
        }

        if (is_string($password) && $password !== '') {
            $redis->auth($password);
        }

        if ($database > 0) {
            $redis->select($database);
        }

        return $redis;
    }

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
