<?php

declare(strict_types=1);

namespace JardisCore\Foundation;

use Exception;
use JardisCore\Foundation\Adapter\DomainKernel;
use JardisCore\Foundation\Adapter\SharedResource;
use JardisPsr\ClassVersion\ClassVersionConfigInterface;
use JardisPsr\ClassVersion\ClassVersionInterface;
use JardisPsr\Foundation\DomainKernelInterface;
use JardisPsr\Foundation\ResourceRegistryInterface;
use JardisSupport\ClassVersion\ClassVersion;
use JardisSupport\ClassVersion\Data\ClassVersionConfig;
use JardisSupport\ClassVersion\Reader\LoadClassFromProxy;
use JardisSupport\ClassVersion\Reader\LoadClassFromSubDirectory;
use ReflectionClass;
use RuntimeException;

/**
 * Base Domain Class
 *
 * Provides kernel access and path detection for domain projects.
 */
class Domain
{
    protected ?string $appRoot = null;
    protected ?string $domainRoot = null;
    protected ?ResourceRegistryInterface $sharedResources = null;
    private DomainKernelInterface $kernel;

    /** @throws Exception */
    protected function getKernel(): DomainKernelInterface
    {
        if (!isset($this->kernel)) {
            $this->kernel = $this->createKernel(
                $this->getAppRoot(),
                $this->getDomainRoot(),
                $this->getClassVersion(),
                $this->getSharedResources(),
                $this->getSharedRuntimeRoot()
            );

            try {
                $this->warmup($this->kernel);
            } catch (Exception) {
                // Warmup failed - continue without it
                // Logger will create own connections if needed
            }
        }

        return $this->kernel;
    }

    /**
     * Creates the domain kernel instance.
     *
     * Override in domain subclasses to provide a domain-specific kernel
     * (e.g., EcommerceKernel extends DomainKernel).
     * @throws Exception
     */
    protected function createKernel(
        string $appRoot,
        string $domainRoot,
        ?ClassVersionInterface $classVersion,
        ResourceRegistryInterface $resources,
        ?string $sharedRuntimeRoot = null
    ): DomainKernelInterface {
        return new DomainKernel($appRoot, $domainRoot, $classVersion, $resources, $sharedRuntimeRoot);
    }

    /**
     * Warmup kernel services to ensure shared resources are available.
     *
     * Initializes Cache early so that REDIS_CACHE is registered in SharedResource
     * before Logger handlers need it. This prevents duplicate connections when
     * Logger and Cache use the same Redis server.
     *
     * Override this method in subclasses to customize warmup behavior.
     * Exceptions thrown here are caught by getKernel() and do not prevent kernel creation.
     *
     * @throws Exception May throw if cache initialization fails (caught by caller)
     */
    protected function warmup(DomainKernelInterface $kernel): void
    {
        $kernel->getCache();
    }

    /**
     * Determine appRoot by backward search for composer.json.
     *
     * Every PHP project has composer.json in its root directory,
     * making it a reliable marker for appRoot detection.
     */
    protected function getAppRoot(): string
    {
        if ($this->appRoot !== null) {
            return $this->appRoot;
        }

        $current = $this->getDomainRoot();

        while ($current !== '/' && $current !== '') {
            if (file_exists($current . '/composer.json')) {
                return $this->appRoot = $current;
            }

            $current = dirname($current);
        }

        // Fallback: parent of domainRoot
        return $this->appRoot = dirname($this->getDomainRoot());
    }

    protected function getDomainRoot(): string
    {
        if ($this->domainRoot === null) {
            $reflection = new ReflectionClass($this);
            $fileName = $reflection->getFileName();
            if ($fileName === false) {
                throw new RuntimeException('Could not determine domain root: reflection file name is false');
            }
            $this->domainRoot = dirname($fileName);
        }

        return $this->domainRoot;
    }

    /**
     * Detects SharedRuntime directory as sibling of domainRoot.
     *
     * Looks for SharedRuntime/ in the parent directory of the domain root.
     * Returns null if the directory does not exist.
     */
    protected function getSharedRuntimeRoot(): ?string
    {
        $parent = dirname($this->getDomainRoot());
        $sharedRuntimePath = $parent . '/SharedRuntime';

        if (is_dir($sharedRuntimePath)) {
            return $sharedRuntimePath;
        }

        return null;
    }

    protected function getClassVersion(
        ?ClassVersionConfigInterface $config = null,
    ): ?ClassVersionInterface {
        $config = $config ?? new ClassVersionConfig();

        return new ClassVersion(
            $config,
            new LoadClassFromSubDirectory($config),
            new LoadClassFromProxy($config),
        );
    }

    protected function getSharedResources(): ResourceRegistryInterface
    {
        return $this->sharedResources ?? SharedResource::registry();
    }
}
