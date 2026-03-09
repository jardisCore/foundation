<?php

declare(strict_types=1);

namespace JardisCore\Foundation;

use Exception;
use JardisCore\Foundation\Adapter\ConnectionProvider;
use JardisCore\Foundation\Adapter\DomainKernel;
use JardisPort\ClassVersion\ClassVersionConfigInterface;
use JardisPort\ClassVersion\ClassVersionInterface;
use JardisPort\Foundation\DomainKernelInterface;
use JardisPort\Foundation\ResourceRegistryInterface;
use JardisSupport\ClassVersion\ClassVersion;
use JardisSupport\ClassVersion\Data\ClassVersionConfig;
use JardisSupport\ClassVersion\Reader\LoadClassFromProxy;
use JardisSupport\ClassVersion\Reader\LoadClassFromSubDirectory;
use ReflectionClass;
use RuntimeException;

/**
 * Base Domain Class
 *
 * Provides kernel access, path detection, and connection setup for domain projects.
 */
class Domain
{
    protected ?string $appRoot = null;
    protected ?string $domainRoot = null;
    private DomainKernelInterface $kernel;

    /** @throws Exception */
    protected function getKernel(): DomainKernelInterface
    {
        if (!isset($this->kernel)) {
            $connections = $this->createConnectionProvider();

            $this->kernel = $this->createKernel(
                $this->getAppRoot(),
                $this->getDomainRoot(),
                $this->getClassVersion(),
                $connections,
                $this->getSharedRuntimeRoot()
            );

            try {
                $this->warmup($this->kernel);
            } catch (Exception) {
                // Warmup failed - continue without it
            }

            // Share connections for cross-domain reuse
            $connections->shareAll();
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
        ConnectionProvider $connections,
        ?string $sharedRuntimeRoot = null
    ): DomainKernelInterface {
        return new DomainKernel($appRoot, $domainRoot, $classVersion, $connections, $sharedRuntimeRoot);
    }

    /**
     * Warmup kernel services to ensure shared resources are available.
     *
     * Initializes Cache early so that Redis connections are shared
     * before Logger handlers need them.
     *
     * @throws Exception May throw if cache initialization fails (caught by caller)
     */
    protected function warmup(DomainKernelInterface $kernel): void
    {
        $kernel->getCache();
    }

    /**
     * Create the ConnectionProvider for this domain.
     *
     * Merges shared connections from other domains (if any).
     * Override to inject external connections.
     */
    protected function createConnectionProvider(): ConnectionProvider
    {
        $connections = new ConnectionProvider();
        $connections->mergeFromShared();

        return $connections;
    }

    /**
     * Determine appRoot by backward search for composer.json.
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
}
