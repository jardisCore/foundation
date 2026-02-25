<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Condition;

use InvalidArgumentException;

/**
 * Parser for condition expressions with AND/OR logic
 *
 * Parses expressions like: "IsVipUser and IsAuthenticated or IsAdmin"
 * Operator precedence: AND binds stronger than OR
 *
 * Grammar:
 * - Expression: OrExpression
 * - OrExpression: AndExpression ('or' AndExpression)*
 * - AndExpression: Condition ('and' Condition)*
 * - Condition: ClassName
 *
 * Examples:
 * - "IsCli" → Single condition
 * - "IsVipUser and IsAuthenticated" → AND
 * - "IsAdmin or IsVipUser" → OR
 * - "IsVipUser and IsAuthenticated or IsAdmin" → (IsVipUser AND IsAuthenticated) OR IsAdmin
 */
class ConditionParser
{
    /**
     * Parse condition expression and return callable.
     *
     * @param string $expression Condition expression (e.g., "IsVipUser and IsAuthenticated or IsAdmin")
     * @param callable(string): callable $conditionLoader Function that loads condition by class name
     * @return callable(): bool Callable that evaluates the expression
     * @throws InvalidArgumentException If expression is invalid
     */
    public function parse(string $expression, callable $conditionLoader): callable
    {
        $expression = trim($expression);

        if ($expression === '') {
            throw new InvalidArgumentException('Condition expression cannot be empty');
        }

        // Split by 'or' (lowest precedence)
        $orParts = $this->splitByOperator($expression, 'or');

        if (count($orParts) === 1) {
            // No OR operator, parse as AND expression
            return $this->parseAndExpression($orParts[0], $conditionLoader);
        }

        // Multiple OR parts: create OR callable
        $orCallables = array_map(
            fn(string $part) => $this->parseAndExpression($part, $conditionLoader),
            $orParts
        );

        return function () use ($orCallables): bool {
            foreach ($orCallables as $callable) {
                if ($callable()) {
                    return true; // Short-circuit OR
                }
            }
            return false;
        };
    }

    /**
     * Parse AND expression (higher precedence than OR).
     *
     * @param string $expression AND expression (e.g., "IsVipUser and IsAuthenticated")
     * @param callable(string): callable $conditionLoader Function that loads condition by class name
     * @return callable(): bool Callable that evaluates the AND expression
     */
    private function parseAndExpression(string $expression, callable $conditionLoader): callable
    {
        // Split by 'and' (higher precedence)
        $andParts = $this->splitByOperator($expression, 'and');

        if (count($andParts) === 1) {
            // Single condition
            $className = trim($andParts[0]);
            return $conditionLoader($className);
        }

        // Multiple AND parts: create AND callable
        $andCallables = array_map(
            function (string $part) use ($conditionLoader): callable {
                $className = trim($part);
                return $conditionLoader($className);
            },
            $andParts
        );

        return function () use ($andCallables): bool {
            foreach ($andCallables as $callable) {
                if (!$callable()) {
                    return false; // Short-circuit AND
                }
            }
            return true;
        };
    }

    /**
     * Split expression by operator (case-insensitive, word boundaries).
     *
     * @param string $expression Expression to split
     * @param string $operator Operator ('and' or 'or')
     * @return array<int, string> Parts of the expression
     */
    private function splitByOperator(string $expression, string $operator): array
    {
        // Use word boundary regex to match operator as whole word (case-insensitive)
        $pattern = '/\s+' . preg_quote($operator, '/') . '\s+/i';
        $parts = preg_split($pattern, $expression);

        if ($parts === false) {
            return [$expression];
        }

        return array_map('trim', $parts);
    }
}
