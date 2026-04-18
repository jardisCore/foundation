<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration;

use JardisAdapter\DbConnection\ConnectionPool;
use JardisCore\Foundation\Handler\ConnectionHandler;
use JardisSupport\Contract\DbConnection\ConnectionPoolInterface;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ConnectionHandler against real database services.
 */
class ConnectionHandlerTest extends TestCase
{
    // ── MySQL ───────────────────────────────────────────────────────

    public function testMysqlReturnsPlainPdo(): void
    {
        $handler = new ConnectionHandler();
        $result = $handler($this->mysqlEnv());

        self::assertInstanceOf(PDO::class, $result);
        self::assertSame('mysql', $result->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function testMysqlPdoCanQuery(): void
    {
        $handler = new ConnectionHandler();
        $pdo = $handler($this->mysqlEnv());

        self::assertInstanceOf(PDO::class, $pdo);

        $stmt = $pdo->query('SELECT 1 AS val');
        $row = $stmt->fetch();

        self::assertEquals(1, $row['val']);
    }

    public function testMysqlPdoHasCorrectAttributes(): void
    {
        $handler = new ConnectionHandler();
        $pdo = $handler($this->mysqlEnv());

        self::assertInstanceOf(PDO::class, $pdo);
        self::assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        self::assertSame(PDO::FETCH_ASSOC, $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    // ── PostgreSQL ──────────────────────────────────────────────────

    public function testPostgresReturnsPlainPdo(): void
    {
        $handler = new ConnectionHandler();
        $result = $handler($this->postgresEnv());

        self::assertInstanceOf(PDO::class, $result);
        self::assertSame('pgsql', $result->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function testPostgresPdoCanQuery(): void
    {
        $handler = new ConnectionHandler();
        $pdo = $handler($this->postgresEnv());

        self::assertInstanceOf(PDO::class, $pdo);

        $stmt = $pdo->query('SELECT 1 AS val');
        $row = $stmt->fetch();

        self::assertSame(1, $row['val']);
    }

    // ── SQLite ──────────────────────────────────────────────────────

    public function testSqliteReturnsPlainPdo(): void
    {
        $handler = new ConnectionHandler();
        $result = $handler($this->sqliteEnv());

        self::assertInstanceOf(PDO::class, $result);
        self::assertSame('sqlite', $result->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    // ── ConnectionPool ──────────────────────────────────────────────

    public function testMysqlWithReadersReturnsConnectionPool(): void
    {
        $handler = new ConnectionHandler();
        $env = array_merge($this->mysqlEnvRaw(), [
            'db_reader1_host' => $_ENV['db_host'] ?? 'mysql_test',
        ]);

        $result = $handler($this->closureFrom($env));

        self::assertInstanceOf(ConnectionPoolInterface::class, $result);
    }

    public function testConnectionPoolWriterCanQuery(): void
    {
        $handler = new ConnectionHandler();
        $env = array_merge($this->mysqlEnvRaw(), [
            'db_reader1_host' => $_ENV['db_host'] ?? 'mysql_test',
        ]);

        $pool = $handler($this->closureFrom($env));

        self::assertInstanceOf(ConnectionPoolInterface::class, $pool);

        $pdo = $pool->getWriter()->pdo();
        $stmt = $pdo->query('SELECT 1 AS val');
        $row = $stmt->fetch();

        self::assertEquals(1, $row['val']);
    }

    public function testPostgresWithReadersReturnsConnectionPool(): void
    {
        $handler = new ConnectionHandler();
        $env = array_merge($this->postgresEnvRaw(), [
            'db_reader1_host' => 'postgres_test',
        ]);

        $result = $handler($this->closureFrom($env));

        self::assertInstanceOf(ConnectionPoolInterface::class, $result);
    }

    // ── No Config / Error Handling ─────────────────────────────────

    public function testNoHostReturnsNull(): void
    {
        $handler = new ConnectionHandler();
        $result = $handler($this->closureFrom([]));

        self::assertNull($result);
    }

    public function testInvalidCredentialsReturnsNull(): void
    {
        $handler = new ConnectionHandler();
        $result = $handler($this->closureFrom([
            'db_driver' => 'mysql',
            'db_host' => 'nonexistent_host_that_does_not_exist',
            'db_port' => '3306',
            'db_user' => 'invalid',
            'db_password' => 'invalid',
            'db_database' => 'invalid',
        ]));

        self::assertNull($result);
    }

    public function testInvalidSqlitePathReturnsNull(): void
    {
        $handler = new ConnectionHandler();
        $result = $handler($this->closureFrom([
            'db_driver' => 'sqlite',
            'db_path' => '/nonexistent/path/that/cannot/be/created/db.sqlite',
        ]));

        self::assertNull($result);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /** @return \Closure(string): mixed */
    private function mysqlEnv(): \Closure
    {
        return $this->closureFrom($this->mysqlEnvRaw());
    }

    /** @return array<string, mixed> */
    private function mysqlEnvRaw(): array
    {
        return [
            'db_driver' => 'mysql',
            'db_host' => $_ENV['db_host'] ?? 'mysql_test',
            'db_port' => $_ENV['db_port'] ?? '3306',
            'db_user' => $_ENV['db_user'] ?? 'app_user',
            'db_password' => $_ENV['db_password'] ?? 'app_secret',
            'db_database' => $_ENV['db_database'] ?? 'test_db',
            'db_charset' => 'utf8mb4',
        ];
    }

    /** @return \Closure(string): mixed */
    private function postgresEnv(): \Closure
    {
        return $this->closureFrom($this->postgresEnvRaw());
    }

    /** @return array<string, mixed> */
    private function postgresEnvRaw(): array
    {
        return [
            'db_driver' => 'pgsql',
            'db_host' => 'postgres_test',
            'db_port' => '5432',
            'db_user' => $_ENV['db_user'] ?? 'app_user',
            'db_password' => $_ENV['db_password'] ?? 'app_secret',
            'db_database' => $_ENV['db_database'] ?? 'test_db',
            'db_charset' => 'utf8',
        ];
    }

    /** @return \Closure(string): mixed */
    private function sqliteEnv(): \Closure
    {
        return $this->closureFrom([
            'db_driver' => 'sqlite',
            'db_path' => ':memory:',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return \Closure(string): mixed
     */
    private function closureFrom(array $data): \Closure
    {
        return static fn (string $key): mixed => $data[strtolower($key)] ?? null;
    }
}
