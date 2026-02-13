<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Condition;

use JardisCore\Foundation\Adapter\Logger\Condition\IsAjaxRequest;
use PHPUnit\Framework\TestCase;

class IsAjaxRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    public function testReturnsTrueForAjaxRequest(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $condition = new IsAjaxRequest();
        $this->assertTrue($condition());
    }

    public function testReturnsTrueForLowercaseXmlHttpRequest(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        $condition = new IsAjaxRequest();
        $this->assertTrue($condition());
    }

    public function testReturnsFalseForNonAjaxRequest(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'SomethingElse';
        $condition = new IsAjaxRequest();
        $this->assertFalse($condition());
    }

    public function testReturnsFalseWhenHeaderNotSet(): void
    {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $condition = new IsAjaxRequest();
        $this->assertFalse($condition());
    }
}
