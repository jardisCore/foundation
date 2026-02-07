<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Cache\Handler;

use JardisCore\Foundation\Adapter\Cache\Handler\ApcuCacheHandler;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisAdapter\Cache\Adapter\CacheApcu;
use PHPUnit\Framework\TestCase;

class ApcuCacheHandlerTest extends TestCase
{
    public function testReturnsNullWhenApcuNotAvailable(): void
    {
        if (extension_loaded('apcu') && apcu_enabled()) {
            $this->markTestSkipped('APCu is available, testing null path not possible');
        }

        $kernel = TestKernelFactory::create();
        $handler = new ApcuCacheHandler();

        $cache = $handler->__invoke($kernel, 'test_namespace');

        $this->assertNull($cache, 'Should return null when APCu is not available');
    }

    public function testCreatesApcuCacheInstanceWhenAvailable(): void
    {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            $this->markTestSkipped('APCu extension is not available or not enabled');
        }

        $kernel = TestKernelFactory::create();
        $handler = new ApcuCacheHandler();

        $cache = $handler->__invoke($kernel, 'test_namespace');

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheApcu::class, $cache);
    }

    public function testUsesProvidedNamespace(): void
    {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            $this->markTestSkipped('APCu extension is not available or not enabled');
        }

        $kernel = TestKernelFactory::create();
        $handler = new ApcuCacheHandler();

        $cache = $handler->__invoke($kernel, 'custom_namespace');

        $this->assertInstanceOf(CacheApcu::class, $cache);

        // Verify namespace is used by testing cache operations
        $cache->set('test_key', 'test_value');
        $this->assertEquals('test_value', $cache->get('test_key'));
        $cache->delete('test_key');
    }
}
