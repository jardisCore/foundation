<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Condition;

use JardisCore\Foundation\Adapter\Logger\Condition\IsLocalhost;
use PHPUnit\Framework\TestCase;

class IsLocalhostTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testReturnsTrueFor127001(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $condition = new IsLocalhost();
        $this->assertTrue($condition());
    }

    public function testReturnsTrueForIpv6Localhost(): void
    {
        $_SERVER['REMOTE_ADDR'] = '::1';
        $condition = new IsLocalhost();
        $this->assertTrue($condition());
    }

    public function testReturnsTrueForLocalhost(): void
    {
        $_SERVER['REMOTE_ADDR'] = 'localhost';
        $condition = new IsLocalhost();
        $this->assertTrue($condition());
    }

    public function testReturnsFalseForRemoteIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $condition = new IsLocalhost();
        $this->assertFalse($condition());
    }

    public function testReturnsFalseWhenRemoteAddrNotSet(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        $condition = new IsLocalhost();
        $this->assertFalse($condition());
    }
}
