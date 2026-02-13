<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Adapter;

use JardisCore\Foundation\Adapter\DomainKernel;
use JardisCore\Foundation\Adapter\ResourceKey;
use JardisCore\Foundation\Adapter\ResourceRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Unit Tests for DomainKernel
 *
 * Tests orchestration logic: factory with/without container, env cascade, resource sharing.
 */
class DomainKernelTest extends TestCase
{
    private string $appRoot;

    protected function setUp(): void
    {
        $this->appRoot = dirname(__DIR__, 2);
    }

    public function testFactoryWithoutContainerInRegistry(): void
    {
        $kernel = new DomainKernel($this->appRoot, $this->appRoot);

        $factory = $kernel->getFactory();

        $this->assertNotNull($factory);
    }

    public function testFactoryWithContainerFromRegistry(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $registry = new ResourceRegistry();
        $registry->register(ResourceKey::CONTAINER->value, $container);

        $kernel = new DomainKernel($this->appRoot, $this->appRoot, null, $registry);

        $factory = $kernel->getFactory();

        $this->assertNotNull($factory);
    }

    public function testGetEnvReturnsNullForUnknownKey(): void
    {
        $kernel = new DomainKernel($this->appRoot, $this->appRoot);

        $this->assertNull($kernel->getEnv('COMPLETELY_UNKNOWN_KEY_XYZ'));
    }

    public function testGetEnvReturnsFullArrayWithoutKey(): void
    {
        $kernel = new DomainKernel($this->appRoot, $this->appRoot);

        $env = $kernel->getEnv();

        $this->assertIsArray($env);
    }

    public function testGetEnvLazyLoadsOnce(): void
    {
        $kernel = new DomainKernel($this->appRoot, $this->appRoot);

        $env1 = $kernel->getEnv();
        $env2 = $kernel->getEnv();

        $this->assertSame($env1, $env2);
    }

    public function testGetResourcesReturnsProvidedRegistry(): void
    {
        $registry = new ResourceRegistry();
        $registry->register('test.key', 'test-value');

        $kernel = new DomainKernel($this->appRoot, $this->appRoot, null, $registry);

        $this->assertSame($registry, $kernel->getResources());
        $this->assertTrue($kernel->getResources()->has('test.key'));
    }

    public function testGetResourcesCreatesDefaultRegistryWhenNoneProvided(): void
    {
        $kernel = new DomainKernel($this->appRoot, $this->appRoot);

        $resources = $kernel->getResources();

        $this->assertInstanceOf(\JardisPsr\Foundation\ResourceRegistryInterface::class, $resources);
    }

    public function testCacheReturnsNullWhenNotConfigured(): void
    {
        $kernel = new DomainKernel($this->appRoot, $this->appRoot);

        // Force environment to disable cache
        $reflection = new \ReflectionClass($kernel);
        $property = $reflection->getProperty('environment');
        $property->setAccessible(true);
        $property->setValue($kernel, [
            'CACHE_MEMORY_ENABLED' => false,
            'CACHE_APCU_ENABLED' => false,
            'CACHE_REDIS_ENABLED' => false,
            'CACHE_DATABASE_ENABLED' => false,
        ]);

        $cache = $kernel->getCache();

        $this->assertNull($cache);
    }

    public function testLoggerReturnsNullWhenNotConfigured(): void
    {
        $kernel = new DomainKernel($this->appRoot, $this->appRoot);

        $reflection = new \ReflectionClass($kernel);
        $property = $reflection->getProperty('environment');
        $property->setAccessible(true);
        $property->setValue($kernel, []);

        $logger = $kernel->getLogger();

        $this->assertNull($logger);
    }

    public function testSharedRuntimeRootIsNullByDefault(): void
    {
        $kernel = new DomainKernel($this->appRoot, $this->appRoot);

        $this->assertNull($kernel->getSharedRuntimeRoot());
    }

    public function testSharedRuntimeRootIsStoredWhenProvided(): void
    {
        $kernel = new DomainKernel(
            $this->appRoot,
            $this->appRoot,
            null,
            null,
            '/path/to/SharedRuntime'
        );

        $this->assertSame('/path/to/SharedRuntime', $kernel->getSharedRuntimeRoot());
    }

    public function testSharedRuntimeOverridesFoundation(): void
    {
        // Create temp SharedRuntime directory with .env
        $tempDir = sys_get_temp_dir() . '/jardis-test-shared-' . uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/.env', "SHARED_TEST_KEY=shared_value\n");

        $kernel = new DomainKernel(
            $this->appRoot,
            $this->appRoot,
            null,
            null,
            $tempDir
        );

        $this->assertSame('shared_value', $kernel->getEnv('SHARED_TEST_KEY'));

        // Cleanup
        unlink($tempDir . '/.env');
        rmdir($tempDir);
    }

    public function testDomainOverridesSharedRuntime(): void
    {
        // Create temp SharedRuntime directory
        $tempShared = sys_get_temp_dir() . '/jardis-test-shared-' . uniqid();
        mkdir($tempShared, 0777, true);
        file_put_contents($tempShared . '/.env', "OVERRIDE_KEY=shared_value\n");

        // Create temp Domain directory
        $tempDomain = sys_get_temp_dir() . '/jardis-test-domain-' . uniqid();
        mkdir($tempDomain, 0777, true);
        file_put_contents($tempDomain . '/.env', "OVERRIDE_KEY=domain_value\n");

        $kernel = new DomainKernel(
            $this->appRoot,
            $tempDomain,
            null,
            null,
            $tempShared
        );

        // Domain should override SharedRuntime (BC is King)
        $this->assertSame('domain_value', $kernel->getEnv('OVERRIDE_KEY'));

        // Cleanup
        unlink($tempShared . '/.env');
        rmdir($tempShared);
        unlink($tempDomain . '/.env');
        rmdir($tempDomain);
    }

    public function testMissingSharedRuntimeDirIsGracefullySkipped(): void
    {
        $kernel = new DomainKernel(
            $this->appRoot,
            $this->appRoot,
            null,
            null,
            '/nonexistent/SharedRuntime'
        );

        // Should not throw, env loading works fine without SharedRuntime
        $env = $kernel->getEnv();
        $this->assertIsArray($env);
    }
}
