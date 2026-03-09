<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Messaging;

use JardisCore\Foundation\Adapter\ConnectionProvider;
use JardisCore\Foundation\Adapter\Messaging\InitMessaging;
use JardisPort\Messaging\MessagingServiceInterface;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * Integration Tests for InitMessaging
 *
 * Tests messaging initialization with ConnectionProvider and config arrays.
 */
class InitMessagingTest extends TestCase
{
    public function testInitializesWithRedisConnection(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $connections = new ConnectionProvider();

        try {
            $redis = new Redis();
            $redis->connect('redis', 6379, 2.5);
            $connections->addRedis('messaging', $redis);
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }

        $initMessaging = new InitMessaging();
        $messaging = $initMessaging($connections, [
            'redis_use_streams' => true,
        ]);

        $this->assertNotNull($messaging);
        $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
    }

    public function testReturnsNullWhenNoTransportAvailable(): void
    {
        $connections = new ConnectionProvider();

        $initMessaging = new InitMessaging();
        $messaging = $initMessaging($connections, []);

        $this->assertNull($messaging);
    }

    public function testInitializesWithInMemoryTransport(): void
    {
        $connections = new ConnectionProvider();

        $initMessaging = new InitMessaging();
        $messaging = $initMessaging($connections, [
            'inmemory_enabled' => true,
            'inmemory_priority' => 99,
        ]);

        $this->assertNotNull($messaging);
        $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
    }

    public function testInitializesWithKafkaConnection(): void
    {
        if (!extension_loaded('rdkafka')) {
            $this->markTestSkipped('rdkafka extension not available');
        }

        $connections = new ConnectionProvider();

        try {
            $conf = new \RdKafka\Conf();
            $conf->set('metadata.broker.list', 'kafka:9092');
            $producer = new \RdKafka\Producer($conf);
            $connections->addKafkaProducer('default', $producer);
        } catch (\Exception $e) {
            $this->markTestSkipped('Kafka not available: ' . $e->getMessage());
        }

        $initMessaging = new InitMessaging();
        $messaging = $initMessaging($connections, []);

        $this->assertNotNull($messaging);
        $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
    }

    public function testInitializesWithRabbitMqConnection(): void
    {
        if (!extension_loaded('amqp')) {
            $this->markTestSkipped('AMQP extension not available');
        }

        $connections = new ConnectionProvider();

        try {
            $amqp = new \AMQPConnection([
                'host' => 'rabbitmq',
                'port' => 5672,
                'login' => 'guest',
                'password' => 'guest',
                'vhost' => '/',
            ]);
            $amqp->connect();
            $connections->addAmqp('default', $amqp);
        } catch (\Exception $e) {
            $this->markTestSkipped('RabbitMQ not available: ' . $e->getMessage());
        }

        $initMessaging = new InitMessaging();
        $messaging = $initMessaging($connections, [
            'rabbitmq_exchange' => 'amq.direct',
            'rabbitmq_queue' => 'test-queue',
        ]);

        $this->assertNotNull($messaging);
        $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
    }

    public function testInitializesWithMultipleTransports(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $connections = new ConnectionProvider();

        try {
            $redis = new Redis();
            $redis->connect('redis', 6379, 2.5);
            $connections->addRedis('messaging', $redis);
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }

        $initMessaging = new InitMessaging();
        $messaging = $initMessaging($connections, [
            'redis_use_streams' => false,
            'inmemory_enabled' => true,
            'inmemory_priority' => 99,
        ]);

        $this->assertNotNull($messaging);
        $this->assertInstanceOf(MessagingServiceInterface::class, $messaging);
    }
}
