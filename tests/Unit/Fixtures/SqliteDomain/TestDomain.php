<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Fixtures\SqliteDomain;

use JardisCore\Foundation\JardisApp;
use JardisSupport\Contract\Kernel\DomainKernelInterface;

/**
 * Test domain with SQLite in-memory database.
 */
class TestDomain extends JardisApp
{
    public function exposedKernel(): DomainKernelInterface
    {
        return $this->kernel();
    }
}
