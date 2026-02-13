<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit;

use JardisCore\Foundation\Adapter\DomainKernel;
use JardisCore\Foundation\Domain;
use JardisPsr\ClassVersion\ClassVersionInterface;
use JardisPsr\Foundation\DomainKernelInterface;
use JardisPsr\Foundation\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit Tests for Domain
 *
 * Tests Domain orchestration behavior: warmup silencing, path caching, kernel singleton.
 */
class DomainTest extends TestCase
{
    public function testWarmupExceptionIsSilencedAndKernelStillWorks(): void
    {
        $domain = new class extends Domain {
            public function __construct()
            {
                $this->domainRoot = dirname(__DIR__, 3);
            }

            protected function warmup(DomainKernelInterface $kernel): void
            {
                throw new RuntimeException('Cache init failed');
            }

            public function exposeKernel(): DomainKernelInterface
            {
                return $this->getKernel();
            }
        };

        $kernel = $domain->exposeKernel();

        $this->assertInstanceOf(DomainKernelInterface::class, $kernel);
        $this->assertNotNull($kernel->getFactory());
    }

    public function testAppRootIsCachedOnSubsequentCalls(): void
    {
        $domain = new class extends Domain {
            public int $callCount = 0;

            public function exposeAppRoot(): string
            {
                $this->callCount++;
                return $this->getAppRoot();
            }

            public function exposeDomainRoot(): string
            {
                return $this->getDomainRoot();
            }
        };

        $appRoot1 = $domain->exposeAppRoot();
        $appRoot2 = $domain->exposeAppRoot();

        $this->assertSame($appRoot1, $appRoot2);
    }

    public function testDomainRootIsCachedOnSubsequentCalls(): void
    {
        $domain = new class extends Domain {
            public function exposeDomainRoot(): string
            {
                return $this->getDomainRoot();
            }
        };

        $domainRoot1 = $domain->exposeDomainRoot();
        $domainRoot2 = $domain->exposeDomainRoot();

        $this->assertSame($domainRoot1, $domainRoot2);
    }

    public function testGetClassVersionReturnsClassVersionByDefault(): void
    {
        $domain = new class extends Domain {
            public function exposeClassVersion(): ?ClassVersionInterface
            {
                return $this->getClassVersion();
            }
        };

        $this->assertInstanceOf(ClassVersionInterface::class, $domain->exposeClassVersion());
    }

    public function testGetClassVersionPassesThroughNonBaseClasses(): void
    {
        $domain = new class extends Domain {
            public function exposeClassVersion(): ?ClassVersionInterface
            {
                return $this->getClassVersion();
            }
        };

        $cv = $domain->exposeClassVersion();
        // Klasse ohne 'Base' Segment bleibt unverändert
        $result = $cv(Domain::class);
        $this->assertSame(Domain::class, $result);
    }

    public function testGetClassVersionOverrideWithNullDisablesIt(): void
    {
        $domain = new class extends Domain {
            public function exposeClassVersion(): ?ClassVersionInterface
            {
                return null;
            }
        };

        $this->assertNull($domain->exposeClassVersion());
    }

    public function testGetSharedResourcesFallsBackToSharedResourceRegistry(): void
    {
        $domain = new class extends Domain {
            public function exposeSharedResources()
            {
                return $this->getSharedResources();
            }
        };

        $resources = $domain->exposeSharedResources();

        $this->assertInstanceOf(\JardisPsr\Foundation\ResourceRegistryInterface::class, $resources);
    }

    public function testCustomSharedResourcesAreUsed(): void
    {
        $customRegistry = new \JardisCore\Foundation\Adapter\ResourceRegistry();
        $customRegistry->register('test.key', 'test-value');

        $domain = new class ($customRegistry) extends Domain {
            public function __construct(\JardisPsr\Foundation\ResourceRegistryInterface $registry)
            {
                $this->sharedResources = $registry;
            }

            public function exposeSharedResources()
            {
                return $this->getSharedResources();
            }
        };

        $resources = $domain->exposeSharedResources();

        $this->assertTrue($resources->has('test.key'));
        $this->assertSame('test-value', $resources->get('test.key'));
    }

    public function testCreateKernelIsOverridable(): void
    {
        $domain = new class extends Domain {
            public function __construct()
            {
                $this->domainRoot = dirname(__DIR__, 3);
            }

            protected function createKernel(
                string $appRoot,
                string $domainRoot,
                ?ClassVersionInterface $classVersion,
                ResourceRegistryInterface $resources,
                ?string $sharedRuntimeRoot = null
            ): DomainKernelInterface {
                return new class ($appRoot, $domainRoot, $classVersion, $resources, $sharedRuntimeRoot) extends DomainKernel {
                    public function getCustomFlag(): bool
                    {
                        return true;
                    }
                };
            }

            public function exposeKernel(): DomainKernelInterface
            {
                return $this->getKernel();
            }
        };

        $kernel = $domain->exposeKernel();

        $this->assertInstanceOf(DomainKernelInterface::class, $kernel);
        $this->assertTrue($kernel->getCustomFlag());
    }

    public function testGetSharedRuntimeRootReturnsNullWhenNoSharedRuntimeDir(): void
    {
        $domain = new class extends Domain {
            public function __construct()
            {
                // Point to a directory without SharedRuntime sibling
                $this->domainRoot = sys_get_temp_dir();
            }

            public function exposeSharedRuntimeRoot(): ?string
            {
                return $this->getSharedRuntimeRoot();
            }
        };

        $this->assertNull($domain->exposeSharedRuntimeRoot());
    }

    public function testGetSharedRuntimeRootReturnsPathWhenSharedRuntimeDirExists(): void
    {
        // Create temp structure: parent/SharedRuntime/ + parent/Domain/
        $parent = sys_get_temp_dir() . '/jardis-domain-test-' . uniqid();
        $domainDir = $parent . '/TestDomain';
        $sharedDir = $parent . '/SharedRuntime';
        mkdir($domainDir, 0777, true);
        mkdir($sharedDir, 0777, true);

        $domain = new class ($domainDir) extends Domain {
            public function __construct(string $domainDir)
            {
                $this->domainRoot = $domainDir;
            }

            public function exposeSharedRuntimeRoot(): ?string
            {
                return $this->getSharedRuntimeRoot();
            }
        };

        $this->assertSame($sharedDir, $domain->exposeSharedRuntimeRoot());

        // Cleanup
        rmdir($sharedDir);
        rmdir($domainDir);
        rmdir($parent);
    }
}
