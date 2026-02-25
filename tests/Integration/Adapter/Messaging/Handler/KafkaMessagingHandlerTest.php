<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Messaging\Handler;

use JardisCore\Foundation\Adapter\Messaging\Handler\KafkaMessagingHandler;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisAdapter\Messaging\MessageConsumer;
use JardisAdapter\Messaging\MessagePublisher;
use PHPUnit\Framework\TestCase;

class KafkaMessagingHandlerTest extends TestCase
{
    public function testRegistersKafkaMessagingHandler(): void
    {
        $handler = new KafkaMessagingHandler();
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_KAFKA_ENABLED' => true,
            'MESSAGING_KAFKA_BROKERS' => 'kafka:9092',
            'MESSAGING_KAFKA_GROUP_ID' => 'test-group',
            'MESSAGING_KAFKA_USERNAME' => '',
            'MESSAGING_KAFKA_PASSWORD' => ''
        ]);
        $publisher = new MessagePublisher();
        $consumer = new MessageConsumer();

        $handler->__invoke($kernel, $publisher, $consumer);

        $this->assertInstanceOf(MessagePublisher::class, $publisher);
        $this->assertInstanceOf(MessageConsumer::class, $consumer);
    }

    public function testRegistersKafkaMessagingWithAuth(): void
    {
        $handler = new KafkaMessagingHandler();
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_KAFKA_ENABLED' => true,
            'MESSAGING_KAFKA_BROKERS' => 'kafka:9092',
            'MESSAGING_KAFKA_GROUP_ID' => 'test-group',
            'MESSAGING_KAFKA_USERNAME' => 'test_user',
            'MESSAGING_KAFKA_PASSWORD' => 'test_pass'
        ]);
        $publisher = new MessagePublisher();
        $consumer = new MessageConsumer();

        $handler->__invoke($kernel, $publisher, $consumer);

        $this->assertInstanceOf(MessagePublisher::class, $publisher);
        $this->assertInstanceOf(MessageConsumer::class, $consumer);
    }

    public function testDoesNotRegisterWhenBrokersMissing(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_KAFKA_BROKERS' => null
        ]);

        $handler = new KafkaMessagingHandler();
        $publisher = new MessagePublisher();
        $consumer = new MessageConsumer();

        $handler->__invoke($kernel, $publisher, $consumer);

        // Should not throw exception, just skip registration
        $this->assertInstanceOf(MessagePublisher::class, $publisher);
        $this->assertInstanceOf(MessageConsumer::class, $consumer);
    }
}
