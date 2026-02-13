<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Condition;

use JardisCore\Foundation\Adapter\Logger\Condition\AlwaysFalse;
use PHPUnit\Framework\TestCase;

class AlwaysFalseTest extends TestCase
{
    public function testAlwaysReturnsFalse(): void
    {
        $condition = new AlwaysFalse();
        $this->assertFalse($condition());
        $this->assertFalse($condition());
        $this->assertFalse($condition());
    }
}
