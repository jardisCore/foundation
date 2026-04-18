<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Handler;

use JardisAdapter\EventDispatcher\EventDispatcher;
use JardisCore\Foundation\Handler\EventDispatcherHandler;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Unit tests for EventDispatcherHandler.
 */
class EventDispatcherHandlerTest extends TestCase
{
    public function testReturnsEventDispatcher(): void
    {
        $handler = new EventDispatcherHandler();
        $result = $handler();

        self::assertInstanceOf(EventDispatcherInterface::class, $result);
        self::assertInstanceOf(EventDispatcher::class, $result);
    }

    public function testDispatcherCanDispatchEvents(): void
    {
        $handler = new EventDispatcherHandler();
        $dispatcher = $handler();

        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $event = new \stdClass();
        $dispatched = $dispatcher->dispatch($event);

        self::assertSame($event, $dispatched);
    }

    public function testRoutesRegisterListeners(): void
    {
        $handler = new EventDispatcherHandler();
        $called = false;

        $route = function ($provider) use (&$called): void {
            $provider->listen(\stdClass::class, function () use (&$called): void {
                $called = true;
            });
        };

        $dispatcher = $handler($route);
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $dispatcher->dispatch(new \stdClass());
        self::assertTrue($called);
    }

    public function testMultipleRoutes(): void
    {
        $handler = new EventDispatcherHandler();
        $log = [];

        $routerA = function ($provider) use (&$log): void {
            $provider->listen(\stdClass::class, function () use (&$log): void {
                $log[] = 'A';
            });
        };

        $routerB = function ($provider) use (&$log): void {
            $provider->listen(\stdClass::class, function () use (&$log): void {
                $log[] = 'B';
            });
        };

        $dispatcher = $handler($routerA, $routerB);
        $dispatcher->dispatch(new \stdClass());

        self::assertSame(['A', 'B'], $log);
    }
}
