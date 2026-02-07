<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Database\Handler;

use Exception;
use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Database\Handler\MySqlConnectionHandler;
use JardisCore\Foundation\Adapter\ResourceRegistry;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisAdapter\DbConnection\External;
use JardisAdapter\DbConnection\MySql;
use PDO;
use PHPUnit\Framework\TestCase;

class MySqlConnectionHandlerTest extends TestCase
{
    public function testReturnsNullWhenNotEnabled(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => false
        ]);

        $handler = new MySqlConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNull($result);
    }

    public function testCreatesConfigFromEnv(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_HOST' => 'mysql_test',
            'DB_WRITER_PORT' => 3306,
            'DB_WRITER_DATABASE' => 'test_db',
            'DB_WRITER_USER' => 'app_user',
            'DB_WRITER_PASSWORD' => 'app_secret',
            'DB_WRITER_CHARSET' => 'utf8mb4'
        ]);

        $handler = new MySqlConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('config', $result);
        $this->assertArrayHasKey('driverClass', $result);
        $this->assertArrayHasKey('persistent', $result);
        $this->assertEquals(MySql::class, $result['driverClass']);
        $this->assertFalse($result['persistent']);
    }

    public function testSupportsPersistentConnection(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_HOST' => 'mysql_test',
            'DB_WRITER_DATABASE' => 'test_db',
            'DB_WRITER_USER' => 'app_user',
            'DB_WRITER_PERSISTENT' => true
        ]);

        $handler = new MySqlConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNotNull($result);
        $this->assertTrue($result['persistent']);
    }

    public function testUsesDefaultValues(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_HOST' => 'mysql_test',
            'DB_WRITER_DATABASE' => 'test_db',
            'DB_WRITER_USER' => 'app_user'
            // PORT and CHARSET not set, should use defaults
        ]);

        $handler = new MySqlConnectionHandler();
        $result = $handler->__invoke($kernel, 'WRITER');

        $this->assertNotNull($result);
        $this->assertEquals(MySql::class, $result['driverClass']);
    }

    public function testThrowsExceptionForMissingHost(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DATABASE' => 'test_db',
            'DB_WRITER_USER' => 'app_user'
            // DB_WRITER_HOST missing
        ]);

        $handler = new MySqlConnectionHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("MySQL connection requires 'DB_WRITER_HOST'");

        $handler->__invoke($kernel, 'WRITER');
    }

    public function testThrowsExceptionForMissingDatabase(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_HOST' => 'mysql_test',
            'DB_WRITER_USER' => 'app_user'
            // DB_WRITER_DATABASE missing
        ]);

        $handler = new MySqlConnectionHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("MySQL connection requires 'DB_WRITER_DATABASE'");

        $handler->__invoke($kernel, 'WRITER');
    }

    public function testThrowsExceptionForMissingUser(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_HOST' => 'mysql_test',
            'DB_WRITER_DATABASE' => 'test_db'
            // DB_WRITER_USER missing
        ]);

        $handler = new MySqlConnectionHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("MySQL connection requires 'DB_WRITER_USER'");

        $handler->__invoke($kernel, 'WRITER');
    }

    public function testCreatesConfigForReader(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'DB_READER1_ENABLED' => true,
            'DB_READER1_HOST' => 'mysql_test',
            'DB_READER1_DATABASE' => 'test_db',
            'DB_READER1_USER' => 'app_user'
        ]);

        $handler = new MySqlConnectionHandler();
        $result = $handler->__invoke($kernel, 'READER1');

        $this->assertNotNull($result);
        $this->assertEquals(MySql::class, $result['driverClass']);
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

        $handler = new MySqlConnectionHandler();
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

        $handler = new MySqlConnectionHandler();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("must be PDO instance");

        $handler->__invoke($kernel, 'WRITER');
    }
}
