<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration;

use JardisCore\Foundation\Domain;
use JardisPsr\Foundation\DomainKernelInterface;
use PHPUnit\Framework\TestCase;

/**
 * Integration Tests for Domain
 *
 * Tests the Domain class with real DomainKernel initialization
 */
class DomainTest extends TestCase
{
    public function testDomainInitializesWithRealKernel(): void
    {
        $domain = new class extends Domain {
            public function exposeKernel()
            {
                return $this->getKernel();
            }
        };

        $kernel = $domain->exposeKernel();

        $this->assertNotNull($kernel);
        $this->assertInstanceOf(DomainKernelInterface::class, $kernel);
    }

    public function testKernelProvidesAllServices(): void
    {
        $domain = new class extends Domain {
            public function exposeKernel()
            {
                return $this->getKernel();
            }
        };

        $kernel = $domain->exposeKernel();

        // Test that core services are accessible
        $this->assertNotNull($kernel->getFactory());
        $this->assertNotNull($kernel->getAppRoot());
        $this->assertNotNull($kernel->getDomainRoot());
    }

    public function testDomainRootPointsToCorrectDirectory(): void
    {
        $domain = new class extends Domain {
            public function exposeDomainRoot(): string
            {
                return $this->getDomainRoot();
            }
        };

        $domainRoot = $domain->exposeDomainRoot();

        $this->assertDirectoryExists($domainRoot);
        $this->assertStringContainsString('tests', $domainRoot);
    }

    public function testAppRootIsDetectedByComposerJson(): void
    {
        $domain = new class extends Domain {
            public function exposeAppRoot(): string
            {
                return $this->getAppRoot();
            }

            public function exposeDomainRoot(): string
            {
                return $this->getDomainRoot();
            }
        };

        $appRoot = $domain->exposeAppRoot();
        $domainRoot = $domain->exposeDomainRoot();

        // appRoot is found by backward search for composer.json
        $this->assertDirectoryExists($appRoot);
        $this->assertStringStartsWith($appRoot, $domainRoot);
        $this->assertFileExists($appRoot . '/composer.json');
    }

    public function testKernelLoadsEnvironmentVariables(): void
    {
        $domain = new class extends Domain {
            public function exposeKernel()
            {
                return $this->getKernel();
            }
        };

        $kernel = $domain->exposeKernel();
        $env = $kernel->getEnv();

        $this->assertIsArray($env);
    }

    public function testMultipleKernelCallsReturnSameInstance(): void
    {
        $domain = new class extends Domain {
            public function exposeKernel()
            {
                return $this->getKernel();
            }
        };

        $kernel1 = $domain->exposeKernel();
        $kernel2 = $domain->exposeKernel();

        $this->assertSame($kernel1, $kernel2, 'Kernel should be singleton within domain');
    }
}
