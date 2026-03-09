<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Condition;

use JardisCore\Foundation\Adapter\Logger\Condition\IsJsonRequest;
use PHPUnit\Framework\TestCase;

class IsJsonRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['CONTENT_TYPE']);
        unset($_SERVER['HTTP_ACCEPT']);
    }

    public function testReturnsTrueWhenContentTypeIsJson(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $condition = new IsJsonRequest();
        $this->assertTrue($condition());
    }

    public function testReturnsTrueWhenContentTypeContainsJson(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json; charset=utf-8';
        $condition = new IsJsonRequest();
        $this->assertTrue($condition());
    }

    public function testReturnsTrueWhenAcceptHeaderIsJson(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $condition = new IsJsonRequest();
        $this->assertTrue($condition());
    }

    public function testReturnsTrueWhenAcceptHeaderContainsJson(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html, application/json, */*';
        $condition = new IsJsonRequest();
        $this->assertTrue($condition());
    }

    public function testReturnsFalseWhenNeitherHeaderIsJson(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'text/html';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $condition = new IsJsonRequest();
        $this->assertFalse($condition());
    }

    public function testReturnsFalseWhenHeadersNotSet(): void
    {
        unset($_SERVER['CONTENT_TYPE']);
        unset($_SERVER['HTTP_ACCEPT']);
        $condition = new IsJsonRequest();
        $this->assertFalse($condition());
    }
}
