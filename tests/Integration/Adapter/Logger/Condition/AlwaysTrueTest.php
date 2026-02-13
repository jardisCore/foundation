<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Condition;

use JardisCore\Foundation\Adapter\Logger\Condition\AlwaysTrue;
use PHPUnit\Framework\TestCase;

class AlwaysTrueTest extends TestCase
{
    public function testAlwaysReturnsTrue(): void
    {
        $condition = new AlwaysTrue();
        $this->assertTrue($condition());
        $this->assertTrue($condition());
        $this->assertTrue($condition());
    }
}
