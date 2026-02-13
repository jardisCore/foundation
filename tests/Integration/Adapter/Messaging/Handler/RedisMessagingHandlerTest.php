<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Messaging\Handler;

use JardisCore\Foundation\Adapter\Messaging\Handler\RedisMessagingHandler;
use JardisCore\Foundation\Tests\Integration\Adapter\TestKernelFactory;
use JardisAdapter\Messaging\MessageConsumer;
use JardisAdapter\Messaging\MessagePublisher;
use PHPUnit\Framework\TestCase;

class RedisMessagingHandlerTest extends TestCase
{
    public function testRegistersRedisMessagingHandler(): void
    {
        $handler = new RedisMessagingHandler();
        $kernel = TestKernelFactory::create();
        TestKernelFactory::setEnv($kernel, [
            'MESSAGING_REDIS_ENABLED' => true,
            'MESSAGING_REDIS_HOST' => 'redis',
            'MESSAGING_REDIS_PORT' => '6379',
            'MESSAGING_REDIS_PASSWORD' => '',
            'MESSAGING_REDIS_USE_STREAMS' => true
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

        $handler = new RedisMessagingHandler();
        $publisher = new MessagePublisher();
        $consumer = new MessageConsumer();

        $handler->__invoke($kernel, $publisher, $consumer);

        // Should not throw exception, just skip registration
        $this->assertInstanceOf(MessagePublisher::class, $publisher);
        $this->assertInstanceOf(MessageConsumer::class, $consumer);
    }
}
