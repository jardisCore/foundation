<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Database;

use JardisCore\Foundation\Adapter\ConnectionProvider;
use JardisCore\Foundation\Adapter\Database\InitDatabase;
use JardisPort\DbConnection\ConnectionPoolInterface;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration Tests for InitDatabase
 *
 * Tests database initialization with ConnectionProvider.
 */
class InitDatabaseTest extends TestCase
{
    public function testInitializesWithWriterPdo(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $connections = new ConnectionProvider();
        $connections->addPdo('writer', $pdo);

        $initDatabase = new InitDatabase();
        $pool = $initDatabase($connections);

        $this->assertNotNull($pool);
        $this->assertInstanceOf(ConnectionPoolInterface::class, $pool);
    }

    public function testReturnsNullWhenNoWriterAvailable(): void
    {
        $connections = new ConnectionProvider();

        $initDatabase = new InitDatabase();
        $pool = $initDatabase($connections);

        $this->assertNull($pool);
    }

    public function testInitializesWithWriterAndSingleReader(): void
    {
        $writerPdo = new PDO('sqlite::memory:');
        $readerPdo = new PDO('sqlite::memory:');

        $connections = new ConnectionProvider();
        $connections->addPdo('writer', $writerPdo);
        $connections->addPdo('reader1', $readerPdo);

        $initDatabase = new InitDatabase();
        $pool = $initDatabase($connections);

        $this->assertNotNull($pool);
        $this->assertInstanceOf(ConnectionPoolInterface::class, $pool);
    }

    public function testInitializesWithMultipleReaders(): void
    {
        $writerPdo = new PDO('sqlite::memory:');
        $reader1Pdo = new PDO('sqlite::memory:');
        $reader2Pdo = new PDO('sqlite::memory:');

        $connections = new ConnectionProvider();
        $connections->addPdo('writer', $writerPdo);
        $connections->addPdo('reader1', $reader1Pdo);
        $connections->addPdo('reader2', $reader2Pdo);

        $initDatabase = new InitDatabase();
        $pool = $initDatabase($connections);

        $this->assertNotNull($pool);
        $this->assertInstanceOf(ConnectionPoolInterface::class, $pool);
    }

    public function testInitializesWithRealMySqlConnection(): void
    {
        try {
            $pdo = new PDO(
                'mysql:host=mysql_test;port=3306;dbname=test_db;charset=utf8mb4',
                'app_user',
                'app_secret',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Exception $e) {
            $this->markTestSkipped('MySQL not available: ' . $e->getMessage());
        }

        $connections = new ConnectionProvider();
        $connections->addPdo('writer', $pdo);

        $initDatabase = new InitDatabase();
        $pool = $initDatabase($connections);

        $this->assertNotNull($pool);
        $this->assertInstanceOf(ConnectionPoolInterface::class, $pool);
    }

    public function testInitializesWithRealPostgresConnection(): void
    {
        try {
            $pdo = new PDO(
                'pgsql:host=postgres_test;port=5432;dbname=test_db',
                'postgres',
                'postgres',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Exception $e) {
            $this->markTestSkipped('PostgreSQL not available: ' . $e->getMessage());
        }

        $connections = new ConnectionProvider();
        $connections->addPdo('writer', $pdo);

        $initDatabase = new InitDatabase();
        $pool = $initDatabase($connections);

        $this->assertNotNull($pool);
        $this->assertInstanceOf(ConnectionPoolInterface::class, $pool);
    }
}
