<?php

declare(strict_types=1);

namespace JardisCore\Foundation;

use JardisCore\Foundation\Handler\CacheHandler;
use JardisCore\Foundation\Handler\ConnectionHandler;
use JardisCore\Foundation\Handler\EventDispatcherHandler;
use JardisCore\Foundation\Handler\FilesystemHandler;
use JardisCore\Foundation\Handler\HttpClientHandler;
use JardisCore\Foundation\Handler\LoggerHandler;
use JardisCore\Foundation\Handler\MailerHandler;
use JardisCore\Foundation\Handler\RedisHandler;
use JardisCore\Kernel\DomainApp;
use JardisSupport\Contract\DbConnection\ConnectionPoolInterface;
use PDO;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use JardisSupport\Contract\Filesystem\FilesystemServiceInterface;
use JardisSupport\Contract\Mailer\MailerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Redis;

/**
 * JardisApp — convenience layer on DomainApp that builds services from ENV.
 *
 * Overrides DomainApp hooks to assemble database, cache and logger from
 * domainRoot/.env configuration. Adapter packages are optional — JardisApp
 * checks availability at runtime via class_exists().
 */
class JardisApp extends DomainApp
{
    private ConnectionPoolInterface|PDO|null $dbConnection = null;
    private ?Redis $redis = null;

    protected function dbConnection(): ConnectionPoolInterface|PDO|false|null
    {
        return $this->dbConnection ??= (new ConnectionHandler())($this->env(...));
    }

    protected function redis(): ?Redis
    {
        return $this->redis ??= (new RedisHandler())($this->env(...));
    }

    protected function cache(): CacheInterface|false|null
    {
        $connection = $this->dbConnection();

        $pdo = match (true) {
            $connection instanceof ConnectionPoolInterface => $connection->getWriter()->pdo(),
            $connection instanceof PDO => $connection,
            default => null,
        };

        return (new CacheHandler())($this->env(...), $pdo, $this->redis());
    }

    protected function logger(): LoggerInterface|false|null
    {
        return (new LoggerHandler())($this->env(...), $this->redis());
    }

    protected function eventDispatcher(): EventDispatcherInterface|false|null
    {
        return (new EventDispatcherHandler())();
    }

    protected function httpClient(): ClientInterface|false|null
    {
        return (new HttpClientHandler())($this->env(...));
    }

    protected function mailer(): MailerInterface|false|null
    {
        return (new MailerHandler())($this->env(...));
    }

    protected function filesystem(): FilesystemServiceInterface|false|null
    {
        return (new FilesystemHandler())();
    }
}
