<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Database;

use JardisAdapter\DbConnection\ConnectionPool;
use JardisAdapter\DbConnection\Data\ConnectionPoolConfig;
use JardisAdapter\DbConnection\Data\ExternalConfig;
use JardisAdapter\DbConnection\External;
use JardisCore\Foundation\Adapter\ConnectionProvider;
use JardisPort\DbConnection\ConnectionPoolInterface;
use JardisPort\DbConnection\DbConnectionInterface;

/**
 * Initialize Database ConnectionPool
 *
 * Assembles ConnectionPool from pre-resolved PDO connections.
 * No ENV reading, no connection creation — pure assembly.
 *
 * Wraps PDO instances from ConnectionProvider into External connections
 * (DbConnectionInterface) for the ConnectionPool adapter.
 */
class InitDatabase
{
    /**
     * Build ConnectionPool from available PDO connections.
     *
     * Returns null if no writer connection is available.
     */
    public function __invoke(ConnectionProvider $connections): ?ConnectionPoolInterface
    {
        $writerPdo = $connections->pdo('writer');
        if ($writerPdo === null) {
            return null;
        }

        $writer = new External(new ExternalConfig($writerPdo));

        /** @var array<DbConnectionInterface> $readers */
        $readers = [];
        for ($i = 1; $connections->hasPdo("reader{$i}"); $i++) {
            $readerPdo = $connections->pdo("reader{$i}");
            if ($readerPdo !== null) {
                $readers[] = new External(new ExternalConfig($readerPdo));
            }
        }

        $poolConfig = new ConnectionPoolConfig(
            usePersistent: false,
            validateConnections: true,
            healthCheckCacheTtl: 30,
            loadBalancingStrategy: ConnectionPoolConfig::STRATEGY_ROUND_ROBIN,
            maxRetries: 3,
            connectionTimeout: 5,
        );

        return new ConnectionPool(
            writer: $writer,
            readers: $readers,
            config: $poolConfig
        );
    }
}
