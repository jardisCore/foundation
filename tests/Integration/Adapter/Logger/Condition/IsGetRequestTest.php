<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Condition;

use JardisCore\Foundation\Adapter\Logger\Condition\IsGetRequest;
use PHPUnit\Framework\TestCase;

class IsGetRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testReturnsTrueForGetRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $condition = new IsGetRequest();
        $this->assertTrue($condition());
    }

    public function testReturnsFalseForPostRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $condition = new IsGetRequest();
        $this->assertFalse($condition());
    }

    public function testReturnsFalseWhenRequestMethodNotSet(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        $condition = new IsGetRequest();
        $this->assertFalse($condition());
    }
}
