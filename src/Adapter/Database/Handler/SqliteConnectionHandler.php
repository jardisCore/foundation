<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Database\Handler;

use Exception;
use InvalidArgumentException;
use JardisAdapter\DbConnection\Data\ExternalConfig;
use JardisAdapter\DbConnection\Data\SqliteConfig;
use JardisAdapter\DbConnection\External;
use JardisAdapter\DbConnection\SqLite;
use JardisCore\Foundation\Adapter\ResourceKey;
use JardisPsr\DbConnection\DbConnectionInterface;
use JardisPsr\Foundation\DomainKernelInterface;
use PDO;

/**
 * SQLite Connection Handler
 *
 * Responsibility: Create SQLite connection configuration from External PDO or ENV.
 *
 * Supports two modes:
 * 1. External PDO: Reuses existing PDO from ResourceRegistry (connection.pdo.{prefix})
 * 2. ENV-based: Creates configuration from environment variables (DB_{PREFIX}_PATH)
 *
 * Required ENV variable: DB_{PREFIX}_PATH (default: ':memory:')
 * Optional ENV variable: DB_{PREFIX}_PERSISTENT
 */
class SqliteConnectionHandler
{
    /**
     * Create SQLite connection configuration.
     *
     * @param DomainKernelInterface $kernel Domain kernel with environment and resources
     * @param string $prefix Connection prefix (WRITER, READER1, READER2, etc.)
     * @return array{
     *     config: SqliteConfig|ExternalConfig,
     *     driverClass: class-string<DbConnectionInterface>,
     *     persistent: bool
     * }|null
     * @throws Exception If external PDO has wrong type or path is invalid
     */
    public function __invoke(DomainKernelInterface $kernel, string $prefix): ?array
    {
        $resources = $kernel->getResources();

        // ===== External PDO First =====
        if ($prefix === 'WRITER') {
            $resourceKey = ResourceKey::PDO_WRITER->value;
        } else {
            // Extract reader index (e.g., READER1 -> 1)
            $readerIndex = (int) substr($prefix, 6);
            $resourceKey = ResourceKey::pdoReader($readerIndex);
        }

        if ($resources->has($resourceKey)) {
            return $this->createFromExternal($resources, $resourceKey);
        }

        // ===== ENV-based Fallback =====
        if (!$kernel->getEnv("DB_{$prefix}_ENABLED")) {
            return null; // Not configured
        }

        return $this->createFromEnv($kernel, $prefix);
    }

    /**
     * Create configuration from external PDO.
     *
     * @param \JardisPsr\Foundation\ResourceRegistryInterface $resources
     * @return array{config: ExternalConfig, driverClass: class-string<DbConnectionInterface>, persistent: bool}
     * @throws Exception If external PDO is not a PDO instance
     */
    private function createFromExternal($resources, string $resourceKey): array
    {
        $externalPdo = $resources->get($resourceKey);

        if (!$externalPdo instanceof PDO) {
            throw new Exception(
                "Resource '{$resourceKey}' must be PDO instance, got " . get_debug_type($externalPdo)
            );
        }

        return [
            'config' => new ExternalConfig($externalPdo),
            'driverClass' => External::class,
            'persistent' => false
        ];
    }

    /**
     * Create configuration from environment variables.
     *
     * @return array{config: SqliteConfig, driverClass: class-string<DbConnectionInterface>, persistent: bool}
     * @throws InvalidArgumentException If path is invalid
     */
    private function createFromEnv(DomainKernelInterface $kernel, string $prefix): array
    {
        $path = $kernel->getEnv("DB_{$prefix}_PATH") ?? ':memory:';

        if (!is_string($path) || trim($path) === '') {
            throw new InvalidArgumentException(
                "SQLite connection requires 'DB_{$prefix}_PATH' environment variable. " .
                "Example: DB_{$prefix}_PATH=/var/db/app.sqlite or DB_{$prefix}_PATH=:memory:"
            );
        }

        $sqliteConfig = new SqliteConfig(path: $path);

        return [
            'config' => $sqliteConfig,
            'driverClass' => SqLite::class,
            'persistent' => (bool) $kernel->getEnv("DB_{$prefix}_PERSISTENT")
        ];
    }
}
