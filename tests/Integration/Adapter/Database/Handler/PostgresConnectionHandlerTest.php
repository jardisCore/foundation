<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Database\Handler;

use Exception;
use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Database\Handler\PostgresConnectionHandler;
use JardisCore\Foundation\Adapter\ResourceRegistry;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisAdapter\DbConnection\External;
use JardisAdapter\DbConnection\Postgres;
use PDO;
use PHPUnit\Framework\TestCase;

class PostgresConnectionHandlerTest extends TestCase
{
    public function testReturnsNullWhenNotEnabled(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => false
        ]);

        $handler = new PostgresConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNull($result);
    }

    public function testCreatesConfigFromEnv(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_HOST' => 'postgres_test',
            'DB_WRITER_PORT' => 5432,
            'DB_WRITER_DATABASE' => 'test_db',
            'DB_WRITER_USER' => 'postgres',
            'DB_WRITER_PASSWORD' => 'postgres'
        ]);

        $handler = new PostgresConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('config', $result);
        $this->assertArrayHasKey('driverClass', $result);
        $this->assertArrayHasKey('persistent', $result);
        $this->assertEquals(Postgres::class, $result['driverClass']);
        $this->assertFalse($result['persistent']);
    }

    public function testSupportsPersistentConnection(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_HOST' => 'postgres_test',
            'DB_WRITER_DATABASE' => 'test_db',
            'DB_WRITER_USER' => 'postgres',
            'DB_WRITER_PERSISTENT' => true
        ]);

        $handler = new PostgresConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNotNull($result);
        $this->assertTrue($result['persistent']);
    }

    public function testUsesDefaultPort(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_HOST' => 'postgres_test',
            'DB_WRITER_DATABASE' => 'test_db',
            'DB_WRITER_USER' => 'postgres'
            // DB_WRITER_PORT not set, should use default
        ]);

        $handler = new PostgresConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNotNull($result);
        $this->assertEquals(Postgres::class, $result['driverClass']);
    }

    public function testThrowsExceptionForMissingHost(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DATABASE' => 'test_db',
            'DB_WRITER_USER' => 'postgres'
            // DB_WRITER_HOST missing
        ]);

        $handler = new PostgresConnectionHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("PostgreSQL connection requires 'DB_WRITER_HOST'");

        $handler->__invoke($kernel, 'WRITER');
    }

    public function testThrowsExceptionForMissingDatabase(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_HOST' => 'postgres_test',
            'DB_WRITER_USER' => 'postgres'
            // DB_WRITER_DATABASE missing
        ]);

        $handler = new PostgresConnectionHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("PostgreSQL connection requires 'DB_WRITER_DATABASE'");

        $handler->__invoke($kernel, 'WRITER');
    }

    public function testThrowsExceptionForMissingUser(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_HOST' => 'postgres_test',
            'DB_WRITER_DATABASE' => 'test_db'
            // DB_WRITER_USER missing
        ]);

        $handler = new PostgresConnectionHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("PostgreSQL connection requires 'DB_WRITER_USER'");

        $handler->__invoke($kernel, 'WRITER');
    }

    public function testCreatesConfigForReader(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_READER1_ENABLED' => true,
            'DB_READER1_HOST' => 'postgres_test',
            'DB_READER1_DATABASE' => 'test_db',
            'DB_READER1_USER' => 'postgres'
        ]);

        $handler = new PostgresConnectionHandler();
        $result = $handler->__invoke($kernel, 'READER1');

        $this->assertNotNull($result);
        $this->assertEquals(Postgres::class, $result['driverClass']);
    }

    public function testUsesExternalPdoWhenAvailable(): void
    {
        $kernel = TestKernelFactory::create();

        // Register external PDO
        $resources = new ResourceRegistry();
        $externalPdo = new PDO('sqlite::memory:'); // Use SQLite for testing
        $resources->register('connection.pdo.writer', $externalPdo);

        // Use reflection to inject resources
        $reflection = new \ReflectionClass($kernel);
        $property = $reflection->getProperty('resources');
        $property->setAccessible(true);
        $property->setValue($kernel, $resources);

        $handler = new PostgresConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNotNull($result);
        $this->assertEquals(External::class, $result['driverClass']);
        $this->assertFalse($result['persistent']);
    }

    public function testThrowsExceptionForInvalidExternalPdo(): void
    {
        $kernel = TestKernelFactory::create();

        // Register invalid resource (not a PDO)
        $resources = new ResourceRegistry();
        $resources->register('connection.pdo.writer', 'not a PDO');

        // Use reflection to inject resources
        $reflection = new \ReflectionClass($kernel);
        $property = $reflection->getProperty('resources');
        $property->setAccessible(true);
        $property->setValue($kernel, $resources);

        $handler = new PostgresConnectionHandler();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("must be PDO instance");

        $handler->__invoke($kernel, 'WRITER');
    }
}
