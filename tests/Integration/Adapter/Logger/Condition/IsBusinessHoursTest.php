<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Condition;

use JardisCore\Foundation\Adapter\Logger\Condition\IsBusinessHours;
use PHPUnit\Framework\TestCase;

class IsBusinessHoursTest extends TestCase
{
    public function testReturnsTrueWhenBetween9And17(): void
    {
        $condition = new IsBusinessHours();
        $hour = (int) date('H');

        if ($hour >= 9 && $hour < 17) {
            $this->assertTrue($condition());
        } else {
            $this->assertFalse($condition());
        }
    }
}
