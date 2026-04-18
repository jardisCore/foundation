<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Fixtures\FullDomain;

use JardisCore\Foundation\JardisApp;
use JardisSupport\Contract\Kernel\DomainKernelInterface;

/**
 * Test domain with all services configured (SQLite, memory cache, null logger).
 */
class TestDomain extends JardisApp
{
    public function exposedKernel(): DomainKernelInterface
    {
        return $this->kernel();
    }
}
