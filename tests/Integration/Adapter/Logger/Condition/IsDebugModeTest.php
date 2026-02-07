<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Condition;

use JardisCore\Foundation\Adapter\Logger\Condition\IsDebugMode;
use PHPUnit\Framework\TestCase;

class IsDebugModeTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['DEBUG']);
        unset($_SERVER['DEBUG']);
    }

    public function testReturnsTrueWhenDebugIsOne(): void
    {
        $_ENV['DEBUG'] = '1';
        $condition = new IsDebugMode();
        $this->assertTrue($condition());
    }

    public function testReturnsTrueWhenDebugIsTrue(): void
    {
        $_ENV['DEBUG'] = 'true';
        $condition = new IsDebugMode();
        $this->assertTrue($condition());
    }

    public function testReturnsTrueWhenDebugIsYes(): void
    {
        $_ENV['DEBUG'] = 'yes';
        $condition = new IsDebugMode();
        $this->assertTrue($condition());
    }

    public function testReturnsTrueWhenDebugIsOn(): void
    {
        $_ENV['DEBUG'] = 'on';
        $condition = new IsDebugMode();
        $this->assertTrue($condition());
    }

    public function testIsCaseInsensitive(): void
    {
        $_ENV['DEBUG'] = 'TRUE';
        $condition = new IsDebugMode();
        $this->assertTrue($condition());
    }

    public function testReturnsFalseWhenDebugIsFalse(): void
    {
        $_ENV['DEBUG'] = 'false';
        $condition = new IsDebugMode();
        $this->assertFalse($condition());
    }

    public function testReturnsFalseWhenDebugNotSet(): void
    {
        unset($_ENV['DEBUG']);
        unset($_SERVER['DEBUG']);
        $condition = new IsDebugMode();
        $this->assertFalse($condition());
    }

    public function testChecksServerVariableWhenEnvNotSet(): void
    {
        unset($_ENV['DEBUG']);
        $_SERVER['DEBUG'] = '1';
        $condition = new IsDebugMode();
        $this->assertTrue($condition());
    }
}
