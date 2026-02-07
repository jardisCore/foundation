<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter;

use JardisCore\Foundation\Adapter\DomainKernel;
use JardisPsr\Foundation\DomainKernelInterface;

/**
 * Factory for creating real DomainKernel instances for integration tests
 */
class TestKernelFactory
{
    /**
     * Create a real DomainKernel with test environment
     *
     * Creates a fresh instance each time - no singleton caching.
     * This ensures test isolation.
     * @throws \Exception
     */
    public static function create(): DomainKernelInterface
    {
        $appRoot = dirname(__DIR__, 2);
        $domainRoot = $appRoot;

        return new DomainKernel($appRoot, $domainRoot);
    }

    /**
     * Set environment variables on a kernel instance
     *
     * Use this ONLY when a test needs to override specific ENV values.
     * For most tests, use create() and rely on .env configuration.
     *
     * @param array<string, mixed> $envOverrides
     */
    public static function setEnv(DomainKernelInterface $kernel, array $envOverrides): void
    {
        // Use reflection to override environment values for testing
        $reflection = new \ReflectionClass($kernel);
        $property = $reflection->getProperty('environment');
        $property->setAccessible(true);

        $currentEnv = $property->getValue($kernel) ?? [];
        $property->setValue($kernel, array_merge($currentEnv, $envOverrides));
    }
}
