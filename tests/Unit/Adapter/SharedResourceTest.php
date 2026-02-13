<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Adapter;

use JardisCore\Foundation\Adapter\ResourceKey;
use JardisCore\Foundation\Adapter\SharedResource;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * Unit tests for SharedResource
 *
 * Tests the new logger setter methods and registry behavior.
 * Note: Kafka and AMQP tests are skipped if extensions not available.
 */
class SharedResourceTest extends TestCase
{
    protected function setUp(): void
    {
        SharedResource::reset();
    }

    protected function tearDown(): void
    {
        SharedResource::reset();
    }

    public function testSetRedisLoggerRegistersConnection(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redis = $this->createMock(Redis::class);

        SharedResource::setRedisLogger($redis);

        $registry = SharedResource::registry();
        $this->assertTrue($registry->has(ResourceKey::REDIS_LOGGER->value));
        $this->assertSame($redis, $registry->get(ResourceKey::REDIS_LOGGER->value));
    }

    public function testSetRedisLoggerDoesNotOverwriteExisting(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redis1 = $this->createMock(Redis::class);
        $redis2 = $this->createMock(Redis::class);

        SharedResource::setRedisLogger($redis1);
        SharedResource::setRedisLogger($redis2);

        $registry = SharedResource::registry();
        $this->assertSame($redis1, $registry->get(ResourceKey::REDIS_LOGGER->value));
    }

    public function testSetRedisLoggerIsSeparateFromRedisCache(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redisLogger = $this->createMock(Redis::class);
        $redisCache = $this->createMock(Redis::class);

        SharedResource::setRedisLogger($redisLogger);
        SharedResource::setRedisCache($redisCache);

        $registry = SharedResource::registry();
        $this->assertSame($redisLogger, $registry->get(ResourceKey::REDIS_LOGGER->value));
        $this->assertSame($redisCache, $registry->get(ResourceKey::REDIS_CACHE->value));
        $this->assertNotSame(
            $registry->get(ResourceKey::REDIS_LOGGER->value),
            $registry->get(ResourceKey::REDIS_CACHE->value)
        );
    }

    public function testRegistryReturnsACopy(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redis = $this->createMock(Redis::class);
        SharedResource::setRedisLogger($redis);

        $registry1 = SharedResource::registry();
        $registry2 = SharedResource::registry();

        // Should be different instances (copies)
        $this->assertNotSame($registry1, $registry2);

        // But contain the same data
        $this->assertEquals($registry1->all(), $registry2->all());
    }

    public function testResetClearsAllResources(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redis = $this->createMock(Redis::class);
        SharedResource::setRedisLogger($redis);

        $registryBefore = SharedResource::registry();
        $this->assertTrue($registryBefore->has(ResourceKey::REDIS_LOGGER->value));

        SharedResource::reset();

        $registryAfter = SharedResource::registry();
        $this->assertFalse($registryAfter->has(ResourceKey::REDIS_LOGGER->value));
    }

    public function testSetPdoWriterRegistersConnection(): void
    {
        $pdo = new \PDO('sqlite::memory:');

        SharedResource::setPdoWriter($pdo);

        $registry = SharedResource::registry();
        $this->assertTrue($registry->has(ResourceKey::PDO_WRITER->value));
        $this->assertSame($pdo, $registry->get(ResourceKey::PDO_WRITER->value));
    }

    public function testSetPdoWriterDoesNotOverwriteExisting(): void
    {
        $pdo1 = new \PDO('sqlite::memory:');
        $pdo2 = new \PDO('sqlite::memory:');

        SharedResource::setPdoWriter($pdo1);
        SharedResource::setPdoWriter($pdo2);

        $registry = SharedResource::registry();
        $this->assertSame($pdo1, $registry->get(ResourceKey::PDO_WRITER->value));
    }

    public function testSetPdoReaderRegistersWithIndex(): void
    {
        $pdo = new \PDO('sqlite::memory:');

        SharedResource::setPdoReader(1, $pdo);

        $registry = SharedResource::registry();
        $this->assertTrue($registry->has(ResourceKey::pdoReader(1)));
        $this->assertSame($pdo, $registry->get(ResourceKey::pdoReader(1)));
    }

    public function testSetPdoReaderDoesNotOverwriteExisting(): void
    {
        $pdo1 = new \PDO('sqlite::memory:');
        $pdo2 = new \PDO('sqlite::memory:');

        SharedResource::setPdoReader(1, $pdo1);
        SharedResource::setPdoReader(1, $pdo2);

        $registry = SharedResource::registry();
        $this->assertSame($pdo1, $registry->get(ResourceKey::pdoReader(1)));
    }

    public function testSetPdoCacheRegistersConnection(): void
    {
        $pdo = new \PDO('sqlite::memory:');

        SharedResource::setPdoCache($pdo);

        $registry = SharedResource::registry();
        $this->assertTrue($registry->has(ResourceKey::PDO_CACHE->value));
        $this->assertSame($pdo, $registry->get(ResourceKey::PDO_CACHE->value));
    }

    public function testSetPdoCacheDoesNotOverwriteExisting(): void
    {
        $pdo1 = new \PDO('sqlite::memory:');
        $pdo2 = new \PDO('sqlite::memory:');

        SharedResource::setPdoCache($pdo1);
        SharedResource::setPdoCache($pdo2);

        $registry = SharedResource::registry();
        $this->assertSame($pdo1, $registry->get(ResourceKey::PDO_CACHE->value));
    }

    public function testSetRedisCacheDoesNotOverwriteExisting(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redis1 = $this->createMock(Redis::class);
        $redis2 = $this->createMock(Redis::class);

        SharedResource::setRedisCache($redis1);
        SharedResource::setRedisCache($redis2);

        $registry = SharedResource::registry();
        $this->assertSame($redis1, $registry->get(ResourceKey::REDIS_CACHE->value));
    }

    public function testSetRedisMessagingRegistersAndGuards(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $redis1 = $this->createMock(Redis::class);
        $redis2 = $this->createMock(Redis::class);

        SharedResource::setRedisMessaging($redis1);
        SharedResource::setRedisMessaging($redis2);

        $registry = SharedResource::registry();
        $this->assertTrue($registry->has(ResourceKey::REDIS_MESSAGING->value));
        $this->assertSame($redis1, $registry->get(ResourceKey::REDIS_MESSAGING->value));
    }

    public function testSetContainerRegistersAndGuards(): void
    {
        $container1 = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container2 = $this->createMock(\Psr\Container\ContainerInterface::class);

        SharedResource::setContainer($container1);
        SharedResource::setContainer($container2);

        $registry = SharedResource::registry();
        $this->assertTrue($registry->has(ResourceKey::CONTAINER->value));
        $this->assertSame($container1, $registry->get(ResourceKey::CONTAINER->value));
    }

    /**
     * @requires extension rdkafka
     */
    public function testSetKafkaLoggerRegistersProducer(): void
    {
        $this->markTestSkipped('Kafka extension not reliably mockable');
    }

    /**
     * @requires extension amqp
     */
    public function testSetAmqpLoggerRegistersConnection(): void
    {
        $this->markTestSkipped('AMQP extension not reliably mockable');
    }
}
