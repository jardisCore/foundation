<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Condition;

use JardisCore\Foundation\Adapter\Logger\Condition\IsPostRequest;
use PHPUnit\Framework\TestCase;

class IsPostRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testReturnsTrueForPostRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $condition = new IsPostRequest();
        $this->assertTrue($condition());
    }

    public function testReturnsFalseForGetRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $condition = new IsPostRequest();
        $this->assertFalse($condition());
    }

    public function testReturnsFalseWhenRequestMethodNotSet(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        $condition = new IsPostRequest();
        $this->assertFalse($condition());
    }
}
