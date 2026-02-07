<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Condition;

use JardisCore\Foundation\Adapter\Logger\Condition\IsWeekend;
use PHPUnit\Framework\TestCase;

class IsWeekendTest extends TestCase
{
    public function testReturnsTrueOnWeekend(): void
    {
        $condition = new IsWeekend();
        $dayOfWeek = (int) date('N');

        // 6 = Saturday, 7 = Sunday
        if (in_array($dayOfWeek, [6, 7], true)) {
            $this->assertTrue($condition());
        } else {
            $this->assertFalse($condition());
        }
    }
}
