<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogDatabase;
use JardisPsr\Foundation\DomainKernelInterface;
use RuntimeException;

/**
 * Database Log Handler
 *
 * Creates database log handler from configuration.
 * Requires active ConnectionPool from DomainKernel.
 *
 * Optional options: 'table' (default: 'logContextData')
 */
class DatabaseLogHandler
{
    public function __invoke(LoggerHandlerConfig $config, DomainKernelInterface $kernel): LogCommandInterface
    {
        $pdo = $kernel->getConnectionPool()?->getWriter()?->pdo();

        if ($pdo === null) {
            throw new RuntimeException(
                "Database handler requires active database connection. " .
                "Ensure DB_WRITER_ENABLED=true in .env"
            );
        }

        $table = $config->getOption('table', 'logContextData');
        if (!is_string($table)) {
            $table = 'logContextData';
        }

        $handler = new LogDatabase($config->level, $pdo, $table);

        return $handler;
    }
}
