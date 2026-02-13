<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Adapter;

use JardisCore\Foundation\Adapter\ResourceKey;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ResourceKey enum
 */
class ResourceKeyTest extends TestCase
{
    public function testRedisLoggerKeyExists(): void
    {
        $this->assertSame('connection.redis.logger', ResourceKey::REDIS_LOGGER->value);
    }

    public function testKafkaLoggerKeyExists(): void
    {
        $this->assertSame('connection.kafka.logger', ResourceKey::KAFKA_LOGGER->value);
    }

    public function testAmqpLoggerKeyExists(): void
    {
        $this->assertSame('connection.amqp.logger', ResourceKey::AMQP_LOGGER->value);
    }

    public function testLoggerKeysAreDifferentFromOtherKeys(): void
    {
        // Redis keys are distinct
        $this->assertNotSame(ResourceKey::REDIS_LOGGER->value, ResourceKey::REDIS_CACHE->value);
        $this->assertNotSame(ResourceKey::REDIS_LOGGER->value, ResourceKey::REDIS_MESSAGING->value);

        // Kafka keys are distinct
        $this->assertNotSame(ResourceKey::KAFKA_LOGGER->value, ResourceKey::KAFKA_PRODUCER->value);
        $this->assertNotSame(ResourceKey::KAFKA_LOGGER->value, ResourceKey::KAFKA_CONSUMER->value);

        // AMQP keys are distinct
        $this->assertNotSame(ResourceKey::AMQP_LOGGER->value, ResourceKey::AMQP->value);
    }

    public function testAllLoggerKeysFollowNamingConvention(): void
    {
        // All logger keys should contain 'logger'
        $this->assertStringContainsString('logger', ResourceKey::REDIS_LOGGER->value);
        $this->assertStringContainsString('logger', ResourceKey::KAFKA_LOGGER->value);
        $this->assertStringContainsString('logger', ResourceKey::AMQP_LOGGER->value);

        // All should start with 'connection.'
        $this->assertStringStartsWith('connection.', ResourceKey::REDIS_LOGGER->value);
        $this->assertStringStartsWith('connection.', ResourceKey::KAFKA_LOGGER->value);
        $this->assertStringStartsWith('connection.', ResourceKey::AMQP_LOGGER->value);
    }

    public function testPdoReaderGeneratesDynamicKeys(): void
    {
        $this->assertSame('connection.pdo.reader1', ResourceKey::pdoReader(1));
        $this->assertSame('connection.pdo.reader2', ResourceKey::pdoReader(2));
        $this->assertSame('connection.pdo.reader10', ResourceKey::pdoReader(10));
    }
}
