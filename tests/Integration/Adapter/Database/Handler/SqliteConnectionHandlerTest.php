<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Database\Handler;

use Exception;
use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Database\Handler\SqliteConnectionHandler;
use JardisCore\Foundation\Adapter\ResourceRegistry;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisAdapter\DbConnection\External;
use JardisAdapter\DbConnection\SqLite;
use PDO;
use PHPUnit\Framework\TestCase;

class SqliteConnectionHandlerTest extends TestCase
{
    public function testReturnsNullWhenNotEnabled(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => false
        ]);

        $handler = new SqliteConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNull($result);
    }

    public function testCreatesConfigFromEnvWithMemoryPath(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_PATH' => ':memory:'
        ]);

        $handler = new SqliteConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('config', $result);
        $this->assertArrayHasKey('driverClass', $result);
        $this->assertArrayHasKey('persistent', $result);
        $this->assertEquals(SqLite::class, $result['driverClass']);
        $this->assertFalse($result['persistent']);
    }

    public function testCreatesConfigFromEnvWithFilePath(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_PATH' => '/tmp/test.db'
        ]);

        $handler = new SqliteConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNotNull($result);
        $this->assertEquals(SqLite::class, $result['driverClass']);
    }

    public function testUsesMemoryPathAsDefault(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true
            // DB_WRITER_PATH not set, should default to :memory:
        ]);

        $handler = new SqliteConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNotNull($result);
        $this->assertEquals(SqLite::class, $result['driverClass']);
    }

    public function testSupportsPersistentConnection(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_PATH' => ':memory:',
            'DB_WRITER_PERSISTENT' => true
        ]);

        $handler = new SqliteConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNotNull($result);
        $this->assertTrue($result['persistent']);
    }

    public function testThrowsExceptionForEmptyPath(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_PATH' => '   ' // Empty after trim
        ]);

        $handler = new SqliteConnectionHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("SQLite connection requires 'DB_WRITER_PATH'");

        $handler->__invoke($kernel, 'WRITER');
    }

    public function testCreatesConfigForReader(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_READER1_ENABLED' => true,
            'DB_READER1_PATH' => ':memory:'
        ]);

        $handler = new SqliteConnectionHandler();
        $result = $handler->__invoke($kernel, 'READER1');

        $this->assertNotNull($result);
        $this->assertEquals(SqLite::class, $result['driverClass']);
    }

    public function testUsesExternalPdoWhenAvailable(): void
    {
        $kernel = TestKernelFactory::create();

        // Register external PDO
        $resources = new ResourceRegistry();
        $externalPdo = new PDO('sqlite::memory:');
        $resources->register('connection.pdo.writer', $externalPdo);

        // Use reflection to inject resources
        $reflection = new \ReflectionClass($kernel);
        $property = $reflection->getProperty('resources');
        $property->setAccessible(true);
        $property->setValue($kernel, $resources);

        $handler = new SqliteConnectionHandler();
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

        $handler = new SqliteConnectionHandler();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("must be PDO instance");

        $handler->__invoke($kernel, 'WRITER');
    }

    public function testUsesExternalPdoForReader(): void
    {
        $kernel = TestKernelFactory::create();

        // Register external PDO for reader1
        $resources = new ResourceRegistry();
        $externalPdo = new PDO('sqlite::memory:');
        $resources->register('connection.pdo.reader1', $externalPdo);

        // Use reflection to inject resources
        $reflection = new \ReflectionClass($kernel);
        $property = $reflection->getProperty('resources');
        $property->setAccessible(true);
        $property->setValue($kernel, $resources);

        $handler = new SqliteConnectionHandler();
        $result = $handler->__invoke($kernel, 'READER1');

        $this->assertNotNull($result);
        $this->assertEquals(External::class, $result['driverClass']);
    }
}
