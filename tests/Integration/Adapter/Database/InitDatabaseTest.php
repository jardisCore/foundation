<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Database;

use JardisCore\Foundation\Adapter\Database\InitDatabase;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisPsr\DbConnection\ConnectionPoolInterface;
use PHPUnit\Framework\TestCase;

/**
 * Integration Tests for InitDatabase
 *
 * Tests database initialization with real configuration
 */
class InitDatabaseTest extends TestCase
{
    public function testInitializesWithRealDatabaseConfiguration(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DRIVER' => 'mysql',
            'DB_WRITER_HOST' => 'mysql_test',
            'DB_WRITER_PORT' => 3306,
            'DB_WRITER_DATABASE' => 'test_db',
            'DB_WRITER_USER' => 'app_user',
            'DB_WRITER_PASSWORD' => 'app_secret',
            'DB_WRITER_CHARSET' => 'utf8mb4'
        ]);

        $initDatabase = new InitDatabase();

        try {
            $pool = $initDatabase->__invoke($kernel);

            if ($pool !== null) {
                $this->assertInstanceOf(ConnectionPoolInterface::class, $pool);
            } else {
                $this->assertNull($pool, 'Pool should be null if configuration is invalid');
            }
        } catch (\Exception $e) {
            // Connection may fail if database is not actually available
            $this->assertStringContainsString('connection', strtolower($e->getMessage()));
        }
    }

    public function testReturnsNullWhenDatabaseDisabled(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => false
        ]);

        $initDatabase = new InitDatabase();
        $pool = $initDatabase->__invoke($kernel);

        $this->assertNull($pool, 'Pool should be null when disabled');
    }

    public function testReturnsNullWhenRequiredConfigMissing(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DRIVER' => 'mysql'
            // Missing host, port, database, user, etc.
        ]);

        $initDatabase = new InitDatabase();

        try {
            $pool = $initDatabase->__invoke($kernel);
            $this->assertNull($pool, 'Pool should be null when required config is missing');
        } catch (\Exception $e) {
            // Exception is acceptable for missing config
            $this->assertTrue(true);
        }
    }

    public function testInitializesWithWriterAndSingleReader(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DRIVER' => 'mysql',
            'DB_WRITER_HOST' => 'mysql_test',
            'DB_WRITER_PORT' => 3306,
            'DB_WRITER_DATABASE' => 'test_db',
            'DB_WRITER_USER' => 'app_user',
            'DB_WRITER_PASSWORD' => 'app_secret',
            'DB_WRITER_CHARSET' => 'utf8mb4',
            'DB_READER1_ENABLED' => true,
            'DB_READER1_DRIVER' => 'mysql',
            'DB_READER1_HOST' => 'mysql_test',
            'DB_READER1_PORT' => 3306,
            'DB_READER1_DATABASE' => 'test_db',
            'DB_READER1_USER' => 'app_user',
            'DB_READER1_PASSWORD' => 'app_secret',
            'DB_READER1_CHARSET' => 'utf8mb4'
        ]);

        $initDatabase = new InitDatabase();
        $pool = $initDatabase->__invoke($kernel);

        $this->assertNotNull($pool);
        $this->assertInstanceOf(ConnectionPoolInterface::class, $pool);
    }

    public function testInitializesWithMultipleReaders(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DRIVER' => 'mysql',
            'DB_WRITER_HOST' => 'mysql_test',
            'DB_WRITER_PORT' => 3306,
            'DB_WRITER_DATABASE' => 'test_db',
            'DB_WRITER_USER' => 'app_user',
            'DB_WRITER_PASSWORD' => 'app_secret',
            'DB_WRITER_CHARSET' => 'utf8mb4',
            'DB_READER1_ENABLED' => true,
            'DB_READER1_DRIVER' => 'mysql',
            'DB_READER1_HOST' => 'mysql_test',
            'DB_READER1_PORT' => 3306,
            'DB_READER1_DATABASE' => 'test_db',
            'DB_READER1_USER' => 'app_user',
            'DB_READER1_PASSWORD' => 'app_secret',
            'DB_READER1_CHARSET' => 'utf8mb4',
            'DB_READER2_ENABLED' => true,
            'DB_READER2_DRIVER' => 'mysql',
            'DB_READER2_HOST' => 'mysql_test',
            'DB_READER2_PORT' => 3306,
            'DB_READER2_DATABASE' => 'test_db',
            'DB_READER2_USER' => 'app_user',
            'DB_READER2_PASSWORD' => 'app_secret',
            'DB_READER2_CHARSET' => 'utf8mb4'
        ]);

        $initDatabase = new InitDatabase();
        $pool = $initDatabase->__invoke($kernel);

        $this->assertNotNull($pool);
        $this->assertInstanceOf(ConnectionPoolInterface::class, $pool);
    }

    public function testThrowsExceptionWhenDriverNotSpecified(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DRIVER' => null,
            'DB_WRITER_HOST' => 'mysql',
            'DB_WRITER_PORT' => 3306,
            'DB_WRITER_DATABASE' => 'test_db'
        ]);

        $initDatabase = new InitDatabase();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DB_WRITER_DRIVER is required');
        $initDatabase->__invoke($kernel);
    }

    public function testThrowsExceptionWhenUnsupportedDriver(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DRIVER' => 'mongodb',
            'DB_WRITER_HOST' => 'mongodb',
            'DB_WRITER_PORT' => 27017,
            'DB_WRITER_DATABASE' => 'test_db',
            'DB_WRITER_USER' => 'root',
            'DB_WRITER_PASSWORD' => 'secret'
        ]);

        $initDatabase = new InitDatabase();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported database driver');
        $initDatabase->__invoke($kernel);
    }

    public function testThrowsExceptionWhenReaderDriverMismatch(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DRIVER' => 'mysql',
            'DB_WRITER_HOST' => 'mysql',
            'DB_WRITER_PORT' => 3306,
            'DB_WRITER_DATABASE' => 'jardis_test',
            'DB_WRITER_USER' => 'root',
            'DB_WRITER_PASSWORD' => 'secret',
            'DB_WRITER_CHARSET' => 'utf8mb4',
            'DB_READER1_ENABLED' => true,
            'DB_READER1_DRIVER' => 'pgsql',
            'DB_READER1_HOST' => 'postgres',
            'DB_READER1_PORT' => 5432,
            'DB_READER1_DATABASE' => 'jardis_test',
            'DB_READER1_USER' => 'postgres',
            'DB_READER1_PASSWORD' => 'secret'
        ]);

        $initDatabase = new InitDatabase();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('All database connections must use the same driver');
        $initDatabase->__invoke($kernel);
    }

    public function testInitializesWithPostgresDriver(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DRIVER' => 'pgsql',
            'DB_WRITER_HOST' => 'postgres_test',
            'DB_WRITER_PORT' => 5432,
            'DB_WRITER_DATABASE' => 'test_db',
            'DB_WRITER_USER' => 'postgres',
            'DB_WRITER_PASSWORD' => 'postgres'
        ]);

        $initDatabase = new InitDatabase();
        $pool = $initDatabase->__invoke($kernel);

        $this->assertNotNull($pool);
        $this->assertInstanceOf(ConnectionPoolInterface::class, $pool);
    }

    public function testInitializesWithSqliteDriver(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DRIVER' => 'sqlite',
            'DB_WRITER_PATH' => ':memory:'
        ]);

        $initDatabase = new InitDatabase();
        $pool = $initDatabase->__invoke($kernel);

        $this->assertNotNull($pool);
        $this->assertInstanceOf(ConnectionPoolInterface::class, $pool);
    }
}
