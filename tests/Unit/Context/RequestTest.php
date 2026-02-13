<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Context;

use JardisCore\Foundation\Context\Request;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Request class.
 */
class RequestTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $clientId = 123;
        $userId = 456;
        $version = '1.0';
        $payload = ['key' => 'value', 'number' => 42];

        $request = new Request($clientId, $userId, $version, $payload);

        $this->assertSame($clientId, $request->clientId);
        $this->assertSame($userId, $request->userId);
        $this->assertSame($version, $request->version);
        $this->assertSame($payload, $request->payload);
    }

    public function testConstructorAcceptsMixedTypes(): void
    {
        $request = new Request('client-uuid', null, 2, []);

        $this->assertSame('client-uuid', $request->clientId);
        $this->assertNull($request->userId);
        $this->assertSame(2, $request->version);
        $this->assertSame([], $request->payload);
    }

    public function testConstructorWithEmptyPayload(): void
    {
        $request = new Request(1, 2, '1.0', []);

        $this->assertSame([], $request->payload);
    }

    public function testConstructorWithComplexPayload(): void
    {
        $payload = [
            'string' => 'text',
            'int' => 123,
            'float' => 45.67,
            'bool' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'nested' => ['key' => 'value'],
        ];

        $request = new Request(1, 2, '1.0', $payload);

        $this->assertSame($payload, $request->payload);
    }

    public function testRequestIsReadonly(): void
    {
        $request = new Request(1, 2, '1.0', ['key' => 'value']);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        // @phpstan-ignore-next-line - Testing readonly violation
        $request->clientId = 999;
    }
}
