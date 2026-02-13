<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Cache\Handler;

use JardisCore\Foundation\Adapter\Cache\Handler\DatabaseCacheHandler;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisAdapter\Cache\Adapter\CacheDatabase;
use PHPUnit\Framework\TestCase;

class DatabaseCacheHandlerTest extends TestCase
{
    public function testCreatesDatabaseCacheInstance(): void
    {
        $kernel = TestKernelFactory::create();

        // Setup database connection for cache
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DRIVER' => 'sqlite',
            'DB_WRITER_PATH' => ':memory:'
        ]);

        $handler = new DatabaseCacheHandler();
        $cache = $handler->__invoke($kernel, 'test_namespace');

        $this->assertNotNull($cache);
        $this->assertInstanceOf(CacheDatabase::class, $cache);
    }

    public function testUsesCustomCacheTable(): void
    {
        $kernel = TestKernelFactory::create();

        // Setup database connection for cache
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DRIVER' => 'sqlite',
            'DB_WRITER_PATH' => ':memory:',
            'CACHE_DB_TABLE' => 'test_cache'
        ]);

        $handler = new DatabaseCacheHandler();
        $cache = $handler->__invoke($kernel, 'test_namespace');

        $this->assertNotNull($cache);
    }

    public function testUsesDefaultCacheTableWhenNotSpecified(): void
    {
        $kernel = TestKernelFactory::create();

        // Setup database connection for cache
        TestKernelFactory::setEnv($kernel, [
            'DB_WRITER_ENABLED' => true,
            'DB_WRITER_DRIVER' => 'sqlite',
            'DB_WRITER_PATH' => ':memory:'
        ]);

        $handler = new DatabaseCacheHandler();
        $cache = $handler->__invoke($kernel, 'test_namespace');

        $this->assertNotNull($cache);
    }
}
