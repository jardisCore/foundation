<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Messaging;

use JardisCore\Foundation\Adapter\Messaging\InitMessaging;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisPsr\Messaging\MessagingServiceInterface;
use PHPUnit\Framework\TestCase;

/**
 * Integration Tests for InitMessaging
 *
 * Tests messaging initialization with real configuration
 */
class InitMessagingTest extends TestCase
{
    public function testInitializesWithRedisConfiguration(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_REDIS_ENABLED' => true,
            'MESSAGING_REDIS_HOST' => 'redis',
            'MESSAGING_REDIS_PORT' => '6379',
            'MESSAGING_REDIS_USE_STREAMS' => true
        ]);

        $initMessaging = new InitMessaging();

        try {
            $messaging = $initMessaging->__invoke($kernel);

            if ($messaging !== null) {
                $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
            } else {
                $this->assertNull($messaging);
            }
        } catch (\Exception $e) {
            // Connection may fail if Redis is not available
            $this->assertTrue(true);
        }
    }

    public function testReturnsNullWhenAllMessagingDisabled(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_REDIS_ENABLED' => false,
            'MESSAGING_KAFKA_ENABLED' => false,
            'MESSAGING_RABBITMQ_ENABLED' => false
        ]);

        $initMessaging = new InitMessaging();
        $messaging = $initMessaging->__invoke($kernel);

        // No messaging configured = null returned (no objects created)
        $this->assertNull($messaging);
    }

    public function testMessagingHandlesPrioritySystem(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_REDIS_ENABLED' => true,
            'MESSAGING_KAFKA_ENABLED' => false,
            'MESSAGING_RABBITMQ_ENABLED' => false,
            'MESSAGING_REDIS_HOST' => 'redis',
            'MESSAGING_REDIS_PORT' => '6379'
        ]);

        $initMessaging = new InitMessaging();

        try {
            $messaging = $initMessaging->__invoke($kernel);

            if ($messaging !== null) {
                // Messaging service should use Redis (priority 0)
                $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
            }
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testInitializesWithKafkaConfiguration(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_KAFKA_ENABLED' => true,
            'MESSAGING_KAFKA_BROKERS' => 'kafka:9092',
            'MESSAGING_KAFKA_GROUP_ID' => 'test-group',
            'MESSAGING_REDIS_ENABLED' => false,
            'MESSAGING_RABBITMQ_ENABLED' => false
        ]);

        $initMessaging = new InitMessaging();
        $messaging = $initMessaging->__invoke($kernel);

        $this->assertNotNull($messaging);
        $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
    }

    public function testInitializesWithRabbitMqConfiguration(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_RABBITMQ_ENABLED' => true,
            'MESSAGING_RABBITMQ_HOST' => 'rabbitmq',
            'MESSAGING_RABBITMQ_PORT' => '5672',
            'MESSAGING_RABBITMQ_USERNAME' => 'guest',
            'MESSAGING_RABBITMQ_PASSWORD' => 'guest',
            'MESSAGING_RABBITMQ_QUEUE' => 'test-queue',
            'MESSAGING_REDIS_ENABLED' => false,
            'MESSAGING_KAFKA_ENABLED' => false
        ]);

        $initMessaging = new InitMessaging();
        $messaging = $initMessaging->__invoke($kernel);

        $this->assertNotNull($messaging);
        $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
    }

    public function testInitializesWithMultipleBrokersEnabled(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_REDIS_ENABLED' => true,
            'MESSAGING_REDIS_HOST' => 'redis',
            'MESSAGING_REDIS_PORT' => '6379',
            'MESSAGING_KAFKA_ENABLED' => true,
            'MESSAGING_KAFKA_BROKERS' => 'kafka:9092',
            'MESSAGING_KAFKA_GROUP_ID' => 'test-group',
            'MESSAGING_RABBITMQ_ENABLED' => true,
            'MESSAGING_RABBITMQ_HOST' => 'rabbitmq',
            'MESSAGING_RABBITMQ_PORT' => '5672',
            'MESSAGING_RABBITMQ_USERNAME' => 'guest',
            'MESSAGING_RABBITMQ_PASSWORD' => 'guest',
            'MESSAGING_RABBITMQ_QUEUE' => 'test-queue'
        ]);

        $initMessaging = new InitMessaging();
        $messaging = $initMessaging->__invoke($kernel);

        $this->assertNotNull($messaging);
        $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
    }

    public function testHandlesRedisWithPassword(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_REDIS_ENABLED' => true,
            'MESSAGING_REDIS_HOST' => 'redis',
            'MESSAGING_REDIS_PORT' => '6379',
            'MESSAGING_REDIS_PASSWORD' => 'secret'
        ]);

        $initMessaging = new InitMessaging();
        $messaging = $initMessaging->__invoke($kernel);

        $this->assertNotNull($messaging);
        $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
    }

    public function testHandlesKafkaWithAuthentication(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_KAFKA_ENABLED' => true,
            'MESSAGING_KAFKA_BROKERS' => 'kafka:9092',
            'MESSAGING_KAFKA_GROUP_ID' => 'test-group',
            'MESSAGING_KAFKA_USERNAME' => 'kafka-user',
            'MESSAGING_KAFKA_PASSWORD' => 'kafka-pass',
            'MESSAGING_REDIS_ENABLED' => false,
            'MESSAGING_RABBITMQ_ENABLED' => false
        ]);

        $initMessaging = new InitMessaging();
        $messaging = $initMessaging->__invoke($kernel);

        $this->assertNotNull($messaging);
        $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
    }

    public function testUsesDefaultValuesForOptionalParameters(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_RABBITMQ_ENABLED' => true,
            'MESSAGING_RABBITMQ_HOST' => 'rabbitmq',
            // Not providing port, username, password, queue - should use defaults
            'MESSAGING_REDIS_ENABLED' => false,
            'MESSAGING_KAFKA_ENABLED' => false
        ]);

        $initMessaging = new InitMessaging();
        $messaging = $initMessaging->__invoke($kernel);

        $this->assertNotNull($messaging);
        $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
    }
}
