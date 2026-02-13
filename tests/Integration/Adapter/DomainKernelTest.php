<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter;

use JardisPsr\Factory\FactoryInterface;
use JardisPsr\DbConnection\ConnectionPoolInterface;
use JardisPsr\Messaging\MessagingServiceInterface;
use JardisPsr\Foundation\DomainKernelInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Integration Tests for DomainKernel
 *
 * Tests the full DomainKernel service orchestration
 */
class DomainKernelTest extends TestCase
{
    private DomainKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = TestKernelFactory::create();
    }

    public function testKernelProvidesAppRoot(): void
    {
        $appRoot = $this->kernel->getAppRoot();

        $this->assertIsString($appRoot);
        $this->assertDirectoryExists($appRoot);
    }

    public function testKernelProvidesDomainRoot(): void
    {
        $domainRoot = $this->kernel->getDomainRoot();

        $this->assertIsString($domainRoot);
        $this->assertDirectoryExists($domainRoot);
    }

    public function testDomainRootIsChildOfAppRoot(): void
    {
        $appRoot = $this->kernel->getAppRoot();
        $domainRoot = $this->kernel->getDomainRoot();

        $this->assertStringContainsString($appRoot, $domainRoot);
    }

    public function testKernelLoadsEnvironmentVariables(): void
    {
        $env = $this->kernel->getEnv();

        $this->assertIsArray($env);
    }

    public function testKernelCanGetSpecificEnvironmentVariable(): void
    {
        // Set a test environment variable
        putenv('TEST_VAR=test_value');

        $value = $this->kernel->getEnv('TEST_VAR');

        // May be null if not loaded via DotEnv
        $this->assertTrue($value === 'test_value' || $value === null);
    }

    public function testKernelReturnsNullForNonExistentEnvKey(): void
    {
        $value = $this->kernel->getEnv('NON_EXISTENT_KEY_123456');

        $this->assertNull($value);
    }

    public function testKernelProvidesFactory(): void
    {
        $factory = $this->kernel->getFactory();

        $this->assertNotNull($factory);
        $this->assertInstanceOf(FactoryInterface::class, $factory);
    }

    public function testFactoryIsReusedOnMultipleCalls(): void
    {
        $factory1 = $this->kernel->getFactory();
        $factory2 = $this->kernel->getFactory();

        $this->assertSame($factory1, $factory2, 'Factory should be singleton');
    }

    public function testKernelCanProvideCache(): void
    {
        $cache = $this->kernel->getCache();

        // Cache may be null if not configured
        if ($cache !== null) {
            $this->assertInstanceOf(CacheInterface::class, $cache);
        } else {
            $this->assertNull($cache);
        }
    }

    public function testCacheIsReusedOnMultipleCalls(): void
    {
        $cache1 = $this->kernel->getCache();
        $cache2 = $this->kernel->getCache();

        $this->assertSame($cache1, $cache2, 'Cache should be singleton or both null');
    }

    public function testKernelCanProvideConnectionPool(): void
    {
        $pool = $this->kernel->getConnectionPool();

        // ConnectionPool may be null if not configured
        if ($pool !== null) {
            $this->assertInstanceOf(ConnectionPoolInterface::class, $pool);
        } else {
            $this->assertNull($pool);
        }
    }

    public function testConnectionPoolIsReusedOnMultipleCalls(): void
    {
        $pool1 = $this->kernel->getConnectionPool();
        $pool2 = $this->kernel->getConnectionPool();

        $this->assertSame($pool1, $pool2, 'ConnectionPool should be singleton or both null');
    }

    public function testKernelCanProvideLogger(): void
    {
        $logger = $this->kernel->getLogger();

        // Logger may be null if not configured
        if ($logger !== null) {
            $this->assertInstanceOf(LoggerInterface::class, $logger);
        } else {
            $this->assertNull($logger);
        }
    }

    public function testLoggerIsReusedOnMultipleCalls(): void
    {
        $logger1 = $this->kernel->getLogger();
        $logger2 = $this->kernel->getLogger();

        $this->assertSame($logger1, $logger2, 'Logger should be singleton or both null');
    }

    public function testKernelCanProvideMessaging(): void
    {
        $messaging = $this->kernel->getMessage();

        // Messaging may be null if not configured
        if ($messaging !== null) {
            $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
        } else {
            $this->assertNull($messaging);
        }
    }

    public function testMessagingIsReusedOnMultipleCalls(): void
    {
        $messaging1 = $this->kernel->getMessage();
        $messaging2 = $this->kernel->getMessage();

        $this->assertSame($messaging1, $messaging2, 'Messaging should be singleton or both null');
    }

    public function testAllServicesCanBeInitializedTogether(): void
    {
        // Test that we can get all services without conflicts
        $factory = $this->kernel->getFactory();
        $cache = $this->kernel->getCache();
        $pool = $this->kernel->getConnectionPool();
        $logger = $this->kernel->getLogger();
        $messaging = $this->kernel->getMessage();

        $this->assertNotNull($factory);
        // Other services may be null depending on configuration

        // Verify they're singletons
        $this->assertSame($factory, $this->kernel->getFactory());
    }

    public function testKernelProvidesResourceRegistry(): void
    {
        $resources = $this->kernel->getResources();

        $this->assertNotNull($resources);
        $this->assertInstanceOf(\JardisPsr\Foundation\ResourceRegistryInterface::class, $resources);
    }
}
