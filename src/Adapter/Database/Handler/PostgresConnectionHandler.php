<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Database\Handler;

use Exception;
use InvalidArgumentException;
use JardisAdapter\DbConnection\Data\ExternalConfig;
use JardisAdapter\DbConnection\Data\PostgresConfig;
use JardisAdapter\DbConnection\External;
use JardisAdapter\DbConnection\Postgres;
use JardisCore\Foundation\Adapter\ResourceKey;
use JardisPsr\DbConnection\DbConnectionInterface;
use JardisPsr\Foundation\DomainKernelInterface;
use PDO;

/**
 * PostgreSQL Connection Handler
 *
 * Responsibility: Create PostgreSQL connection configuration from External PDO or ENV.
 *
 * Supports two modes:
 * 1. External PDO: Reuses existing PDO from ResourceRegistry (connection.pdo.{prefix})
 * 2. ENV-based: Creates configuration from environment variables (DB_{PREFIX}_*)
 *
 * Required ENV variables: DB_{PREFIX}_HOST, DB_{PREFIX}_USER, DB_{PREFIX}_DATABASE
 * Optional ENV variables: DB_{PREFIX}_PASSWORD, DB_{PREFIX}_PORT, DB_{PREFIX}_PERSISTENT
 */
class PostgresConnectionHandler
{
    /**
     * Create PostgreSQL connection configuration.
     *
     * @param DomainKernelInterface $kernel Domain kernel with environment and resources
     * @param string $prefix Connection prefix (WRITER, READER1, READER2, etc.)
     * @return array{
     *     config: PostgresConfig|ExternalConfig,
     *     driverClass: class-string<DbConnectionInterface>,
     *     persistent: bool
     * }|null
     * @throws Exception If external PDO has wrong type or required ENV options are missing
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
     * @return array{config: PostgresConfig, driverClass: class-string<DbConnectionInterface>, persistent: bool}
     * @throws InvalidArgumentException If required options are missing
     */
    private function createFromEnv(DomainKernelInterface $kernel, string $prefix): array
    {
        $host = $kernel->getEnv("DB_{$prefix}_HOST");
        $user = $kernel->getEnv("DB_{$prefix}_USER");
        $database = $kernel->getEnv("DB_{$prefix}_DATABASE");

        // Validate required options
        $this->validateRequired($prefix, [
            'HOST' => $host,
            'USER' => $user,
            'DATABASE' => $database
        ]);

        $postgresConfig = new PostgresConfig(
            host: $host,
            user: $user,
            password: $kernel->getEnv("DB_{$prefix}_PASSWORD") ?? '',
            database: $database,
            port: (int) ($kernel->getEnv("DB_{$prefix}_PORT") ?? 5432)
        );

        return [
            'config' => $postgresConfig,
            'driverClass' => Postgres::class,
            'persistent' => (bool) $kernel->getEnv("DB_{$prefix}_PERSISTENT")
        ];
    }

    /**
     * Validate required environment variables.
     *
     * @param array<string, mixed> $options
     * @throws InvalidArgumentException
     */
    private function validateRequired(string $prefix, array $options): void
    {
        foreach ($options as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                throw new InvalidArgumentException(
                    "PostgreSQL connection requires 'DB_{$prefix}_{$key}' environment variable. " .
                    "Example: DB_{$prefix}_HOST=localhost, DB_{$prefix}_USER=postgres, DB_{$prefix}_DATABASE=app_db"
                );
            }
        }
    }
}
