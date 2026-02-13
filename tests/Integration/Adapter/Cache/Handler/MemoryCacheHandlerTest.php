<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Cache\Handler;

use JardisCore\Foundation\Adapter\Cache\Handler\MemoryCacheHandler;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisAdapter\Cache\Adapter\CacheMemory;
use PHPUnit\Framework\TestCase;

class MemoryCacheHandlerTest extends TestCase
{
    public function testCreatesMemoryCacheInstance(): void
    {
        $kernel = TestKernelFactory::create();
        $handler = new MemoryCacheHandler();

        $cache = $handler->__invoke($kernel, 'test_namespace');

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheMemory::class, $cache);
    }
}
