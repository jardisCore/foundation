<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Fixtures\EmptyDomain;

use JardisCore\Foundation\JardisApp;
use JardisSupport\Contract\Kernel\DomainKernelInterface;

/**
 * Test domain with no .env — all hooks return null.
 */
class TestDomain extends JardisApp
{
    public function exposedKernel(): DomainKernelInterface
    {
        return $this->kernel();
    }
}
