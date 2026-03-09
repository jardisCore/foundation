<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter;

use Exception;
use JardisCore\Foundation\Adapter\Cache\InitCache;
use JardisCore\Foundation\Adapter\Database\InitDatabase;
use JardisCore\Foundation\Adapter\Logger\InitLogger;
use JardisCore\Foundation\Adapter\Messaging\InitMessaging;
use JardisSupport\DotEnv\DotEnv;
use JardisSupport\Factory\Factory;
use JardisPort\Foundation\ResourceRegistryInterface;
use JardisPort\Messaging\MessagingServiceInterface;
use JardisPort\ClassVersion\ClassVersionInterface;
use JardisPort\Factory\FactoryInterface;
use JardisSupport\Data\DataService;
use JardisSupport\Repository\Repository;
use JardisPort\DbConnection\ConnectionPoolInterface;
use JardisPort\Foundation\DomainKernelInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Domain Kernel
 *
 * Central orchestrator for all domain services (Cache, Database, Logger, Messaging).
 * Uses ConnectionProvider for all infrastructure connections.
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
    private ?ClassVersionInterface $classVersion;
    private ConnectionProvider $connections;
    private ResourceRegistryInterface $resources;
    private ?string $sharedRuntimeRoot;
    private bool $envConfigured = false;

    /** @throws Exception */
    public function __construct(
        string $appRoot,
        string $domainRoot,
        ?ClassVersionInterface $classVersion = null,
        ?ConnectionProvider $connections = null,
        ?string $sharedRuntimeRoot = null,
        ?ResourceRegistryInterface $resources = null,
    ) {
        $this->appRoot = $appRoot;
        $this->domainRoot = $domainRoot;
        $this->classVersion = $classVersion;
        $this->connections = $connections ?? new ConnectionProvider();
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

    /**
     * @return mixed|array<string, mixed>
     * @throws Exception
     */
    public function getEnv(?string $key = null): mixed
    {
        if ($this->environment === null) {
            $this->loadEnvironment();
        }

        if ($key === null) {
            return $this->environment;
        }

        return $this->environment[$key] ?? null;
    }

    /**
     * Get the ConnectionProvider for typed connection access.
     */
    public function getConnections(): ConnectionProvider
    {
        $this->ensureEnvConnections();
        return $this->connections;
    }

    /** @throws Exception */
    public function getFactory(): ?FactoryInterface
    {
        /** @var ?ContainerInterface $container */
        $container = $this->resources->has('service.container')
            ? $this->resources->get('service.container')
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

    /** @throws Exception */
    public function getCache(): ?CacheInterface
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->ensureEnvConnections();

        /** @var array<string, mixed> $env */
        $env = $this->getEnv();

        $this->cache = (new InitCache())($this->connections, [
            'namespace' => $env['CACHE_NAMESPACE'] ?? 'app',
            'memory_enabled' => ($env['CACHE_MEMORY_ENABLED'] ?? null) !== 'false',
            'apcu_enabled' => (bool) ($env['CACHE_APCU_ENABLED'] ?? false),
            'db_enabled' => (bool) ($env['CACHE_DB_ENABLED'] ?? false),
            'db_table' => $env['CACHE_DB_TABLE'] ?? 'cache',
        ]);

        // Share new connections for cross-domain reuse
        $this->connections->shareAll();

        return $this->cache;
    }

    /** @throws Exception */
    public function getConnectionPool(): ?ConnectionPoolInterface
    {
        if ($this->connectionPool !== null) {
            return $this->connectionPool;
        }

        $this->ensureEnvConnections();
        $this->connectionPool = (new InitDatabase())($this->connections);

        // Share new connections for cross-domain reuse
        $this->connections->shareAll();

        return $this->connectionPool;
    }

    /** @throws Exception */
    public function getLogger(): ?LoggerInterface
    {
        if ($this->logger !== null) {
            return $this->logger;
        }

        $this->ensureEnvConnections();
        $this->logger = (new InitLogger())($this, $this->connections);

        return $this->logger;
    }

    /** @throws Exception */
    public function getMessage(): ?MessagingServiceInterface
    {
        if ($this->messagingService !== null) {
            return $this->messagingService;
        }

        $this->ensureEnvConnections();

        /** @var array<string, mixed> $env */
        $env = $this->getEnv();

        $this->messagingService = (new InitMessaging())($this->connections, [
            'redis_use_streams' => (bool) ($env['MESSAGING_REDIS_USE_STREAMS'] ?? false),
            'rabbitmq_exchange' => $env['MESSAGING_RABBITMQ_EXCHANGE'] ?? 'amq.direct',
            'rabbitmq_queue' => $env['MESSAGING_RABBITMQ_QUEUE'] ?? 'jardis-queue',
            'inmemory_enabled' => (bool) ($env['MESSAGING_INMEMORY_ENABLED'] ?? false),
            'inmemory_priority' => (int) ($env['MESSAGING_INMEMORY_PRIORITY'] ?? 99),
            'kafka_group_id' => $env['MESSAGING_KAFKA_GROUP_ID'] ?? 'jardis-consumer-group',
        ]);

        // Share new connections for cross-domain reuse
        $this->connections->shareAll();

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
        $dotEnv->loadPublic($this->appRoot);

        // 2. Load Foundation defaults (private)
        $foundationRoot = dirname(__DIR__, 2);
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

        // 4. Load Domain environment (private, BC is King)
        $domainEnv = $dotEnv->loadPrivate($this->domainRoot);
        $this->environment = array_merge($this->environment, $domainEnv);
    }

    /**
     * Ensure ENV-based connections are registered on the ConnectionProvider.
     *
     * Called lazily before any service initialization.
     */
    private function ensureEnvConnections(): void
    {
        if ($this->envConfigured) {
            return;
        }

        $this->envConfigured = true;

        /** @var array<string, mixed> $env */
        $env = $this->getEnv();
        $this->connections->configureFromEnv($env);
    }
}
