<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration\Adapter\Logger\Condition;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\Condition\ConditionParser;
use PHPUnit\Framework\TestCase;

class ConditionParserTest extends TestCase
{
    private ConditionParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ConditionParser();
    }

    public function testParseSingleCondition(): void
    {
        $loader = fn($name) => match ($name) {
            'AlwaysTrue' => fn() => true,
            default => throw new InvalidArgumentException("Unknown condition: $name"),
        };

        $condition = $this->parser->parse('AlwaysTrue', $loader);
        $this->assertTrue($condition());
    }

    public function testParseAndCondition(): void
    {
        $loader = fn($name) => match ($name) {
            'True' => fn() => true,
            'False' => fn() => false,
            default => throw new InvalidArgumentException("Unknown condition: $name"),
        };

        $condition = $this->parser->parse('True and False', $loader);
        $this->assertFalse($condition());

        $condition = $this->parser->parse('True and True', $loader);
        $this->assertTrue($condition());
    }

    public function testParseOrCondition(): void
    {
        $loader = fn($name) => match ($name) {
            'True' => fn() => true,
            'False' => fn() => false,
            default => throw new InvalidArgumentException("Unknown condition: $name"),
        };

        $condition = $this->parser->parse('False or True', $loader);
        $this->assertTrue($condition());

        $condition = $this->parser->parse('False or False', $loader);
        $this->assertFalse($condition());
    }

    public function testAndHasHigherPrecedenceThanOr(): void
    {
        $loader = fn($name) => match ($name) {
            'True' => fn() => true,
            'False' => fn() => false,
            default => throw new InvalidArgumentException("Unknown condition: $name"),
        };

        // False or True and False
        // Should be evaluated as: False or (True and False)
        // = False or False
        // = False
        $condition = $this->parser->parse('False or True and False', $loader);
        $this->assertFalse($condition());

        // True or False and False
        // Should be: True or (False and False)
        // = True or False
        // = True
        $condition = $this->parser->parse('True or False and False', $loader);
        $this->assertTrue($condition());
    }

    public function testComplexExpression(): void
    {
        $loader = fn($name) => match ($name) {
            'True' => fn() => true,
            'False' => fn() => false,
            default => throw new InvalidArgumentException("Unknown condition: $name"),
        };

        // True and True or False and True
        // = (True and True) or (False and True)
        // = True or False
        // = True
        $condition = $this->parser->parse('True and True or False and True', $loader);
        $this->assertTrue($condition());
    }

    public function testShortCircuitEvaluation(): void
    {
        $callCount = 0;
        $loader = fn($name) => match ($name) {
            'False' => fn() => false,
            'Counter' => function () use (&$callCount) {
                $callCount++;
                return true;
            },
            default => throw new InvalidArgumentException("Unknown condition: $name"),
        };

        // False and Counter - Counter should not be called (short-circuit)
        $condition = $this->parser->parse('False and Counter', $loader);
        $this->assertFalse($condition());
        $this->assertEquals(0, $callCount, 'Counter should not be called due to short-circuit');
    }

    public function testShortCircuitOrEvaluation(): void
    {
        $callCount = 0;
        $loader = fn($name) => match ($name) {
            'True' => fn() => true,
            'Counter' => function () use (&$callCount) {
                $callCount++;
                return false;
            },
            default => throw new InvalidArgumentException("Unknown condition: $name"),
        };

        // True or Counter - Counter should not be called (short-circuit)
        $condition = $this->parser->parse('True or Counter', $loader);
        $this->assertTrue($condition());
        $this->assertEquals(0, $callCount, 'Counter should not be called due to short-circuit');
    }

    public function testTrimsWhitespace(): void
    {
        $loader = fn($name) => match ($name) {
            'True' => fn() => true,
            'False' => fn() => false,
            default => throw new InvalidArgumentException("Unknown condition: $name"),
        };

        // Test with extra whitespace
        $condition = $this->parser->parse('  True   and   False  ', $loader);
        $this->assertFalse($condition());
    }

    public function testEmptyExpressionThrowsException(): void
    {
        $loader = fn($name) => fn() => true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Condition expression cannot be empty');

        $this->parser->parse('', $loader);
    }
}
