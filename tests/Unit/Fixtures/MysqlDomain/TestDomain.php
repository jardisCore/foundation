<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Fixtures\MysqlDomain;

use JardisCore\Foundation\JardisApp;
use JardisSupport\Contract\Kernel\DomainKernelInterface;

/**
 * Test domain with MySQL writer configuration.
 */
class TestDomain extends JardisApp
{
    public function exposedKernel(): DomainKernelInterface
    {
        return $this->kernel();
    }
}
