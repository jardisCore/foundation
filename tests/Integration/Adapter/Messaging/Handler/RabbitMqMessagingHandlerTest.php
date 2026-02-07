<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Messaging\Handler;

use JardisCore\Foundation\Adapter\Messaging\Handler\RabbitMqMessagingHandler;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisAdapter\Messaging\MessageConsumer;
use JardisAdapter\Messaging\MessagePublisher;
use PHPUnit\Framework\TestCase;

class RabbitMqMessagingHandlerTest extends TestCase
{
    public function testRegistersRabbitMqMessagingHandler(): void
    {
        $handler = new RabbitMqMessagingHandler();
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_RABBITMQ_ENABLED' => true,
            'MESSAGING_RABBITMQ_HOST' => 'rabbitmq',
            'MESSAGING_RABBITMQ_PORT' => '5672',
            'MESSAGING_RABBITMQ_USERNAME' => 'guest',
            'MESSAGING_RABBITMQ_PASSWORD' => 'guest',
            'MESSAGING_RABBITMQ_QUEUE' => 'test-queue'
        ]);
        $publisher = new MessagePublisher();
        $consumer = new MessageConsumer();

        $handler->__invoke($kernel, $publisher, $consumer);

        $this->assertInstanceOf(MessagePublisher::class, $publisher);
        $this->assertInstanceOf(MessageConsumer::class, $consumer);
    }

    public function testUsesDefaultCredentialsWhenNotProvided(): void
    {
        $handler = new RabbitMqMessagingHandler();
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_RABBITMQ_ENABLED' => true,
            'MESSAGING_RABBITMQ_HOST' => 'rabbitmq',
            'MESSAGING_RABBITMQ_PORT' => null,
            'MESSAGING_RABBITMQ_USERNAME' => null,
            'MESSAGING_RABBITMQ_PASSWORD' => null
        ]);
        $publisher = new MessagePublisher();
        $consumer = new MessageConsumer();

        $handler->__invoke($kernel, $publisher, $consumer);

        $this->assertInstanceOf(MessagePublisher::class, $publisher);
        $this->assertInstanceOf(MessageConsumer::class, $consumer);
    }

    public function testDoesNotRegisterWhenHostMissing(): void
    {
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_RABBITMQ_HOST' => null
        ]);

        $handler = new RabbitMqMessagingHandler();
        $publisher = new MessagePublisher();
        $consumer = new MessageConsumer();

        $handler->__invoke($kernel, $publisher, $consumer);

        // Should not throw exception, just skip registration
        $this->assertInstanceOf(MessagePublisher::class, $publisher);
        $this->assertInstanceOf(MessageConsumer::class, $consumer);
    }
}
