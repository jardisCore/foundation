<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Condition;

use JardisCore\Foundation\Adapter\Logger\Condition\IsApiRequest;
use PHPUnit\Framework\TestCase;

class IsApiRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_URI']);
    }

    public function testReturnsTrueWhenRequestUriStartsWithApi(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/users/123';
        $condition = new IsApiRequest();
        $this->assertTrue($condition());
    }

    public function testReturnsFalseWhenRequestUriDoesNotStartWithApi(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin/dashboard';
        $condition = new IsApiRequest();
        $this->assertFalse($condition());
    }

    public function testReturnsFalseWhenRequestUriIsNotSet(): void
    {
        unset($_SERVER['REQUEST_URI']);
        $condition = new IsApiRequest();
        $this->assertFalse($condition());
    }
}
