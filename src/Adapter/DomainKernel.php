<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter;

use Exception;
use JardisCore\Foundation\Adapter\Cache\InitCache;
use JardisCore\Foundation\Adapter\Database\InitDatabase;
use JardisCore\Foundation\Adapter\Logger\InitLogger;
use JardisCore\Foundation\Adapter\Messaging\InitMessaging;
use JardisAdapter\Cache\Adapter\CacheDatabase;
use JardisAdapter\Cache\Adapter\CacheRedis;
use JardisAdapter\Cache\Cache;
use JardisSupport\DotEnv\DotEnv;
use JardisSupport\Factory\Factory;
use JardisAdapter\Messaging\Connection\ExternalKafkaConnection;
use JardisAdapter\Messaging\Connection\ExternalRabbitMqConnection;
use JardisAdapter\Messaging\Connection\ExternalRedisConnection;
use JardisAdapter\Messaging\Connection\KafkaConnection;
use JardisAdapter\Messaging\Connection\RabbitMqConnection;
use JardisAdapter\Messaging\Connection\RedisConnection;
use JardisAdapter\Messaging\MessagePublisher;
use JardisAdapter\Messaging\MessagingService;
use JardisPsr\Foundation\ResourceRegistryInterface;
use JardisPsr\Messaging\Exception\ConnectionException;
use JardisPsr\Messaging\MessagingServiceInterface;
use JardisPsr\ClassVersion\ClassVersionInterface;
use JardisPsr\Factory\FactoryInterface;
use JardisSupport\Data\DataService;
use JardisSupport\Repository\Repository;
use JardisPsr\DbConnection\ConnectionPoolInterface;
use JardisPsr\Foundation\DomainKernelInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use RdKafka\Producer;

/**
 * Domain Kernel
 *
 * Central orchestrator for all domain services (Cache, Database, Logger, Messaging).
 */
class DomainKernel implements DomainKernelInterface
{
    /** @var array<string, mixed>|null */
    private ?array $environment = null;
    private string $appRoot;
    private string $domainRoot;
    private ?FactoryInterface $factory = null;
    private ?CacheInterface $cache = null;
    private ?ConnectionPoolInterface $connectionPool = null;
    private ?LoggerInterface $logger = null;
    private ?MessagingServiceInterface $messagingService = null;
    private ?ClassVersionInterface $classVersion = null;
    private ResourceRegistryInterface $resources;

    private ?string $sharedRuntimeRoot;

    /** @throws Exception */
    public function __construct(
        string $appRoot,
        string $domainRoot,
        ClassVersionInterface $classVersion = null,
        ResourceRegistryInterface $resources = null,
        ?string $sharedRuntimeRoot = null
    ) {
        $this->appRoot = $appRoot;
        $this->domainRoot = $domainRoot;
        $this->classVersion = $classVersion;
        $this->resources = $resources ?? new ResourceRegistry();
        $this->sharedRuntimeRoot = $sharedRuntimeRoot;
    }

    public function getSharedRuntimeRoot(): ?string
    {
        return $this->sharedRuntimeRoot;
    }

    public function getAppRoot(): string
    {
        return $this->appRoot;
    }

    public function getDomainRoot(): string
    {
        return $this->domainRoot;
    }

    /** @return mixed|array<string, mixed>
     * @throws Exception
     */
    public function getEnv(?string $key = null): mixed
    {
        // Lazy load environment on first access
        if ($this->environment === null) {
            $this->loadEnvironment();
        }

        if ($key === null) {
            return $this->environment;
        }

        return $this->environment[$key] ?? null;
    }

    /**
     * @throws Exception
     */
    public function getFactory(): ?FactoryInterface
    {
        /** @var ?ContainerInterface $container */
        $container = $this->resources->has(ResourceKey::CONTAINER->value)
            ? $this->resources->get(ResourceKey::CONTAINER->value)
            : null;
        if ($this->factory === null) {
            $this->factory = new Factory($container, $this->classVersion);
            $this->factory->registerShared([
                Repository::class,
                DataService::class,
            ]);
        }

        return $this->factory;
    }

    /**
     * @throws Exception
     */
    public function getCache(): ?CacheInterface
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = (new InitCache())($this);
        $this->shareCacheConnections();

        return $this->cache;
    }

    /** @throws Exception */
    public function getConnectionPool(): ?ConnectionPoolInterface
    {
        if ($this->connectionPool !== null) {
            return $this->connectionPool;
        }

        $this->connectionPool = (new InitDatabase())($this);
        $this->shareDatabaseConnections();

        return $this->connectionPool;
    }

    /**
     * Share database connections to SharedResource for cross-domain reuse.
     */
    private function shareDatabaseConnections(): void
    {
        $pool = $this->connectionPool;
        if ($pool === null) {
            return;
        }

        SharedResource::setPdoWriter($pool->getWriter()->pdo());

        foreach ($pool->getReaders() as $index => $reader) {
            SharedResource::setPdoReader($index + 1, $reader->pdo());
        }
    }

    /**
     * @throws Exception
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger = $this->logger ?? (new InitLogger())($this);
    }

    /**
     * @throws Exception
     */
    public function getMessage(): ?MessagingServiceInterface
    {
        if ($this->messagingService !== null) {
            return $this->messagingService;
        }

        $this->messagingService = (new InitMessaging())($this);
        $this->shareMessagingConnections();

        return $this->messagingService;
    }

    public function getResources(): ResourceRegistryInterface
    {
        return $this->resources;
    }

    /**
     * Load environment variables from .env files in cascade order.
     *
     * Load order: AppRoot(public) → Foundation(private) → SharedRuntime(private) → Domain(private)
     * Later values override earlier ones (BC is a King principle)
     * @throws Exception
     */
    private function loadEnvironment(): void
    {
        if ($this->environment !== null) {
            return;
        }

        $dotEnv = new DotEnv();
        $this->environment = [];

        // 1. Load AppRoot environment (public, available globally via $_ENV)
        // This allows standalone domain usage and provides base for variable substitution
        $dotEnv->loadPublic($this->appRoot);

        // 2. Load Foundation defaults (private, can use ${VAR} substitution from AppRoot)
        $foundationRoot = dirname(__DIR__, 2); // src/Kernel → project root
        if (is_dir($foundationRoot) && file_exists($foundationRoot . '/.env')) {
            $foundationEnv = $dotEnv->loadPrivate($foundationRoot);
            $this->environment = array_merge($this->environment, $foundationEnv);
        }

        // 3. Load SharedRuntime (private, organization-wide infrastructure config)
        if (
            $this->sharedRuntimeRoot !== null
            && is_dir($this->sharedRuntimeRoot)
            && file_exists($this->sharedRuntimeRoot . '/.env')
        ) {
            $sharedEnv = $dotEnv->loadPrivate($this->sharedRuntimeRoot);
            $this->environment = array_merge($this->environment, $sharedEnv);
        }

        // 4. Load Domain environment (private, can use ${VAR} substitution, BC is King)
        $domainEnv = $dotEnv->loadPrivate($this->domainRoot);
        $this->environment = array_merge($this->environment, $domainEnv);
    }

    /**
     * Share cache connections to SharedResource for cross-domain reuse.
     */
    private function shareCacheConnections(): void
    {
        if (!$this->cache instanceof Cache) {
            return;
        }

        foreach ($this->cache->getLayers() as $layer) {
            if ($layer instanceof CacheRedis) {
                SharedResource::setRedisCache($layer->getConnection());
            } elseif ($layer instanceof CacheDatabase) {
                SharedResource::setPdoCache($layer->getConnection());
            }
        }
    }

    /**
     * Share messaging connections to SharedResource for cross-domain reuse.
     *
     * Actively connects lazy connections before sharing. Connection failures
     * are caught silently - the application continues without sharing, and
     * messaging will retry connection on first actual use.
     */
    private function shareMessagingConnections(): void
    {
        if (!$this->messagingService instanceof MessagingService) {
            return;
        }

        $publisher = $this->messagingService->getPublisher();
        if (!$publisher instanceof MessagePublisher) {
            return;
        }

        $this->shareRedisMessagingConnection($publisher);
        $this->shareKafkaMessagingConnection($publisher);
        $this->shareRabbitMqMessagingConnection($publisher);
    }

    /**
     * Share Kafka messaging connection if available.
     */
    private function shareKafkaMessagingConnection(MessagePublisher $publisher): void
    {
        $connection = $publisher->getConnection('kafka');

        if ($connection instanceof KafkaConnection) {
            try {
                if (!$connection->isConnected()) {
                    $connection->connect();
                }
                SharedResource::setKafkaProducer($connection->getClient());
            } catch (ConnectionException) {
                // Connection failed - no sharing, messaging will retry on first use
            }
        } elseif ($connection instanceof ExternalKafkaConnection && $connection->isConnected()) {
            $client = $connection->getClient();
            if ($client instanceof Producer) {
                SharedResource::setKafkaProducer($client);
            }
        }
    }

    /**
     * Share Redis messaging connection if available.
     */
    private function shareRedisMessagingConnection(MessagePublisher $publisher): void
    {
        $connection = $publisher->getConnection('redis');

        if ($connection instanceof RedisConnection) {
            try {
                if (!$connection->isConnected()) {
                    $connection->connect();
                }
                SharedResource::setRedisMessaging($connection->getClient());
            } catch (ConnectionException) {
                // Connection failed - no sharing, messaging will retry on first use
            }
        } elseif ($connection instanceof ExternalRedisConnection && $connection->isConnected()) {
            SharedResource::setRedisMessaging($connection->getClient());
        }
    }

    /**
     * Share RabbitMQ messaging connection if available.
     */
    private function shareRabbitMqMessagingConnection(MessagePublisher $publisher): void
    {
        $connection = $publisher->getConnection('rabbitmq');

        if ($connection instanceof RabbitMqConnection) {
            try {
                if (!$connection->isConnected()) {
                    $connection->connect();
                }
                SharedResource::setAmqp($connection->getConnection());
            } catch (ConnectionException) {
                // Connection failed - no sharing, messaging will retry on first use
            }
        } elseif ($connection instanceof ExternalRabbitMqConnection && $connection->isConnected()) {
            SharedResource::setAmqp($connection->getConnection());
        }
    }
}
