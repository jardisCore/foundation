<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Adapter;

use JardisCore\Foundation\Adapter\ResourceRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ResourceRegistryTest extends TestCase
{
    private ResourceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ResourceRegistry();
    }

    public function testRegisterAndGetResource(): void
    {
        $resource = new \stdClass();
        $resource->value = 'test';

        $this->registry->register('test.resource', $resource);

        $retrieved = $this->registry->get('test.resource');
        $this->assertSame($resource, $retrieved);
        $this->assertEquals('test', $retrieved->value);
    }

    public function testHasReturnsTrueForRegisteredResource(): void
    {
        $this->registry->register('test.resource', 'value');

        $this->assertTrue($this->registry->has('test.resource'));
    }

    public function testHasReturnsFalseForUnregisteredResource(): void
    {
        $this->assertFalse($this->registry->has('non.existent'));
    }

    public function testGetThrowsExceptionForUnregisteredResource(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Resource 'non.existent' not registered");

        $this->registry->get('non.existent');
    }

    public function testAllReturnsAllRegisteredResources(): void
    {
        $resource1 = new \stdClass();
        $resource2 = 'test string';
        $resource3 = ['array' => 'value'];

        $this->registry->register('resource.1', $resource1);
        $this->registry->register('resource.2', $resource2);
        $this->registry->register('resource.3', $resource3);

        $all = $this->registry->all();

        $this->assertCount(3, $all);
        $this->assertSame($resource1, $all['resource.1']);
        $this->assertSame($resource2, $all['resource.2']);
        $this->assertSame($resource3, $all['resource.3']);
    }

    public function testAllReturnsEmptyArrayWhenNoResources(): void
    {
        $this->assertSame([], $this->registry->all());
    }

    public function testUnregisterRemovesResource(): void
    {
        $this->registry->register('test.resource', 'value');
        $this->assertTrue($this->registry->has('test.resource'));

        $this->registry->unregister('test.resource');
        $this->assertFalse($this->registry->has('test.resource'));
    }

    public function testUnregisterNonExistentResourceDoesNotThrow(): void
    {
        $this->registry->unregister('non.existent');
        $this->assertTrue(true); // No exception thrown
    }

    public function testRegisterOverwritesExistingResource(): void
    {
        $this->registry->register('test.resource', 'old value');
        $this->registry->register('test.resource', 'new value');

        $this->assertEquals('new value', $this->registry->get('test.resource'));
    }

    public function testRegisterDifferentResourceTypes(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $string = 'test';
        $array = ['key' => 'value'];
        $object = new \stdClass();

        $this->registry->register('connection.pdo.writer', $pdo);
        $this->registry->register('string.resource', $string);
        $this->registry->register('array.resource', $array);
        $this->registry->register('object.resource', $object);

        $this->assertInstanceOf(\PDO::class, $this->registry->get('connection.pdo.writer'));
        $this->assertIsString($this->registry->get('string.resource'));
        $this->assertIsArray($this->registry->get('array.resource'));
        $this->assertIsObject($this->registry->get('object.resource'));
    }
}
