<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Condition;

use JardisCore\Foundation\Adapter\Logger\Condition\IsCli;
use PHPUnit\Framework\TestCase;

class IsCliTest extends TestCase
{
    public function testReturnsTrue(): void
    {
        // PHPUnit runs in CLI mode
        $condition = new IsCli();
        $this->assertTrue($condition());
    }
}
