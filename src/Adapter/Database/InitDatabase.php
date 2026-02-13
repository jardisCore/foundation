<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Database;

use Exception;
use JardisCore\Foundation\Adapter\Database\Handler\MySqlConnectionHandler;
use JardisCore\Foundation\Adapter\Database\Handler\PostgresConnectionHandler;
use JardisCore\Foundation\Adapter\Database\Handler\SqliteConnectionHandler;
use JardisAdapter\DbConnection\ConnectionPool;
use JardisAdapter\DbConnection\Data\ConnectionPoolConfig;
use JardisAdapter\DbConnection\Data\DatabaseConfig;
use JardisPsr\DbConnection\ConnectionPoolInterface;
use JardisPsr\Foundation\DomainKernelInterface;

/**
 * Database Initialization
 *
 * Responsibility: Orchestrate database handler selection and build ConnectionPool.
 *
 * Determines which handler to use based on driver configuration:
 * - Checks for external PDO connections in ResourceRegistry
 * - Checks for ENV configuration (DB_WRITER_ENABLED, DB_WRITER_DRIVER)
 * - Only instantiates handler when actually configured (Lazy Loading)
 *
 * Delegates configuration logic to driver-specific handlers:
 * - MySqlConnectionHandler: MySQL/MariaDB configuration
 * - PostgresConnectionHandler: PostgreSQL configuration
 * - SqliteConnectionHandler: SQLite configuration
 *
 * Each handler supports:
 * 1. External PDO from ResourceRegistry (connection.pdo.{prefix})
 * 2. ENV-based configuration (DB_{PREFIX}_*)
 *
 * Environment variables:
 * - DB_WRITER_ENABLED: Enable writer connection (required)
 * - DB_WRITER_DRIVER: Database driver (mysql, pgsql, sqlite)
 * - DB_WRITER_HOST, DB_WRITER_USER, etc. (driver-specific)
 * - DB_READER1_ENABLED, DB_READER1_DRIVER, etc. (optional, for load balancing)
 */
class InitDatabase
{
    /**
     * Initialize database connection pool.
     *
     * Returns null if no database is configured at all.
     * Only instantiates handlers that are actually needed (lazy loading).
     *
     * @throws Exception If configuration is invalid or driver mismatch occurs
     */
    public function __invoke(DomainKernelInterface $kernel): ?ConnectionPoolInterface
    {
        // Early exit: No database configured
        if (!$this->isDatabaseConfigured($kernel)) {
            return null;
        }

        // Determine driver (needed for handler selection and validation)
        $driver = $this->determineDriver($kernel);

        // Lazy: Get appropriate handler for this driver
        $handler = $this->getHandlerForDriver($driver);

        // Configure Writer
        $writerResult = $handler($kernel, 'WRITER');
        if ($writerResult === null) {
            return null; // Should not happen after isDatabaseConfigured, but be safe
        }

        $writerConfig = $writerResult['config'];
        $driverClass = $writerResult['driverClass'];
        $usePersistent = $writerResult['persistent'];

        // Configure Readers (lazy loop)
        $readerConfigs = $this->configureReaders($kernel, $handler, $driver);

        // Build ConnectionPool
        $poolConfig = new ConnectionPoolConfig(
            usePersistent: $usePersistent,
            validateConnections: !$kernel->getResources()->has('connection.pdo.writer'),
            healthCheckCacheTtl: 30,
            loadBalancingStrategy: ConnectionPoolConfig::STRATEGY_ROUND_ROBIN,
            maxRetries: 3,
            connectionTimeout: 5,
        );

        $pool = new ConnectionPool(
            writer: $writerConfig,
            readers: $readerConfigs,
            driverClass: $driverClass,
            config: $poolConfig
        );

        return $pool;
    }

    /**
     * Check if database is configured (External or ENV).
     */
    private function isDatabaseConfigured(DomainKernelInterface $kernel): bool
    {
        return $kernel->getResources()->has('connection.pdo.writer')
            || $kernel->getEnv('DB_WRITER_ENABLED');
    }

    /**
     * Determine database driver.
     *
     * @throws Exception If driver is not configured when using ENV
     */
    private function determineDriver(DomainKernelInterface $kernel): string
    {
        // External mode: driver is determined by handler (default to mysql for validation)
        if ($kernel->getResources()->has('connection.pdo.writer')) {
            return 'mysql'; // Not used for external, but needed for reader validation
        }

        // ENV mode: driver must be specified
        $driver = $kernel->getEnv('DB_WRITER_DRIVER');
        if (!$driver || !is_string($driver)) {
            throw new Exception('DB_WRITER_DRIVER is required when DB_WRITER_ENABLED=true');
        }

        return strtolower($driver);
    }

    /**
     * Get handler instance for driver (lazy instantiation).
     *
     * @throws Exception If driver is unsupported
     */
    private function getHandlerForDriver(
        string $driver
    ): MySqlConnectionHandler|PostgresConnectionHandler|SqliteConnectionHandler {
        return match ($driver) {
            'mysql' => new MySqlConnectionHandler(),
            'pgsql' => new PostgresConnectionHandler(),
            'sqlite' => new SqliteConnectionHandler(),
            default => throw new Exception("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * Configure reader connections (lazy loop).
     *
     * @return array<DatabaseConfig>
     * @throws Exception If reader driver mismatches writer driver
     */
    private function configureReaders(
        DomainKernelInterface $kernel,
        MySqlConnectionHandler|PostgresConnectionHandler|SqliteConnectionHandler $handler,
        string $writerDriver
    ): array {
        /** @var array<DatabaseConfig> $readerConfigs */
        $readerConfigs = [];
        $readerIndex = 1;

        // Lazy loop: Only create readers that exist
        while (true) {
            $readerResult = $handler($kernel, "READER{$readerIndex}");

            if ($readerResult === null) {
                break; // No more readers configured
            }

            // Validate driver match for ENV-based readers
            if (!$kernel->getResources()->has("connection.pdo.reader{$readerIndex}")) {
                $readerDriver = $kernel->getEnv("DB_READER{$readerIndex}_DRIVER");
                if ($readerDriver !== $writerDriver) {
                    throw new Exception(
                        "All database connections must use the same driver. " .
                        "Writer uses '{$writerDriver}', but Reader{$readerIndex} uses '{$readerDriver}'"
                    );
                }
            }

            $readerConfigs[] = $readerResult['config'];
            $readerIndex++;
        }

        return $readerConfigs;
    }
}
