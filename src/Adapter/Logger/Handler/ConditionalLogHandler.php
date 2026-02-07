<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\Condition\ConditionParser;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Handler\LogConditional;
use JardisPsr\Foundation\DomainKernelInterface;

/**
 * Conditional Log Handler
 *
 * Routes log messages to handlers based on runtime conditions.
 * Supports complex expressions with AND/OR logic.
 *
 * Required configuration:
 * - 'wraps': Comma-separated list of handler names
 * - 'conditions': Boolean expression with AND/OR operators
 *
 * Condition Expression Syntax:
 * - Single: `IsCli`
 * - AND: `IsVipUser and IsAuthenticated`
 * - OR: `IsAdmin or IsVipUser`
 * - Combined: `IsVipUser and IsAuthenticated or IsAdmin`
 * - Precedence: AND binds stronger than OR
 *
 * Example .env configuration:
 * ```
 * LOG_HANDLER1_TYPE=file
 * LOG_HANDLER1_NAME=vip_file
 * LOG_HANDLER1_PATH=/var/log/vip.log
 *
 * LOG_HANDLER2_TYPE=slack
 * LOG_HANDLER2_NAME=admin_alerts
 * LOG_HANDLER2_WEBHOOK=https://...
 *
 * LOG_HANDLER3_TYPE=conditional
 * LOG_HANDLER3_WRAPS=vip_file,admin_alerts
 * LOG_HANDLER3_CONDITIONS=IsVipUser and IsAuthenticated or IsAdmin
 * ```
 *
 * Predefined Condition Classes (in vendor):
 * - IsCli: Running in CLI mode
 * - IsApiRequest: Request URI starts with /api
 * - IsWeekend: Saturday or Sunday
 * - IsBusinessHours: 9 AM - 5 PM
 * - AlwaysTrue: Always evaluates to true
 * - AlwaysFalse: Always evaluates to false
 *
 * Custom Conditions (in project):
 * Create classes in: src/Foundation/Logger/Condition/
 * Must implement __invoke(): bool
 *
 * Use Case: All wrapped handlers are called when condition expression evaluates to true.
 */
class ConditionalLogHandler
{
    /**
     * Create conditional handler that wraps multiple handlers.
     *
     * All wrapped handlers are called when the condition expression evaluates to true.
     *
     * @param LoggerHandlerConfig $config Handler configuration
     * @param DomainKernelInterface $kernel Domain kernel (not used, kept for interface compatibility)
     * @param array<int, LogCommandInterface> $wrappedHandlers The handlers to wrap (injected by factory)
     * @return LogCommandInterface
     * @throws InvalidArgumentException If configuration is invalid
     */
    public function __invoke(
        LoggerHandlerConfig $config,
        DomainKernelInterface $kernel,
        array $wrappedHandlers
    ): LogCommandInterface {
        // Get condition expression
        $conditionExpression = $config->getOption('conditions');
        if ($conditionExpression === null || (is_string($conditionExpression) && trim($conditionExpression) === '')) {
            throw new InvalidArgumentException(
                "Conditional handler requires 'conditions' parameter. " .
                "Example: LOG_HANDLER{N}_CONDITIONS=IsCli or IsVipUser and IsAuthenticated"
            );
        }

        if (!is_string($conditionExpression)) {
            throw new InvalidArgumentException(
                "Conditional handler 'conditions' must be a string expression"
            );
        }

        // Validate that at least one wrapped handler is provided
        if (empty($wrappedHandlers)) {
            throw new InvalidArgumentException(
                "Conditional handler requires at least one wrapped handler. " .
                "Example: LOG_HANDLER{N}_WRAPS=handler_name"
            );
        }

        // Ensure all handlers are streamable
        foreach ($wrappedHandlers as $index => $handler) {
            if (!$handler instanceof StreamableLogCommandInterface) {
                throw new InvalidArgumentException(
                    "Wrapped handler at index {$index} must implement " .
                    "StreamableLogCommandInterface for conditional routing"
                );
            }
        }

        // Parse condition expression into callable
        $parser = new ConditionParser();
        $conditionCallable = $parser->parse(
            $conditionExpression,
            fn(string $className) => $this->loadCondition($className)
        );

        // Build conditional handlers array: All handlers use the same condition
        /** @var array<int, array{callable(): mixed, StreamableLogCommandInterface}> $conditionalHandlers */
        $conditionalHandlers = [];
        foreach ($wrappedHandlers as $handler) {
            /** @var StreamableLogCommandInterface $handler */
            $conditionalHandlers[] = [$conditionCallable, $handler];
        }

        return new LogConditional($conditionalHandlers);
    }

    /**
     * Load condition class and return callable.
     *
     * Looks for condition class in:
     * 1. Project: App\Kernel\Logger\Condition\{ClassName}
     * 2. Vendor: JardisCore\Foundation\Adapter\Logger\Condition\{ClassName}
     *
     * @param string $className Condition class name (e.g., "IsCli", "IsVipUser")
     * @return callable(): bool Callable that evaluates the condition
     * @throws InvalidArgumentException If condition class not found or not invokable
     */
    private function loadCondition(string $className): callable
    {
        $className = trim($className);

        if ($className === '') {
            throw new InvalidArgumentException('Condition class name cannot be empty');
        }

        // Try project namespace first (allows override)
        $projectClass = "App\\Foundation\\Logger\\Condition\\{$className}";
        if (class_exists($projectClass)) {
            $instance = new $projectClass();
            if (is_callable($instance)) {
                return $instance;
            }
            throw new InvalidArgumentException(
                "Condition class '{$projectClass}' is not invokable (must implement __invoke(): bool)"
            );
        }

        // Try vendor namespace
        $vendorClass = "JardisCore\\Foundation\\Adapter\\Logger\\Condition\\{$className}";
        if (class_exists($vendorClass)) {
            $instance = new $vendorClass();
            if (is_callable($instance)) {
                return $instance;
            }
            throw new InvalidArgumentException(
                "Condition class '{$vendorClass}' is not invokable (must implement __invoke(): bool)"
            );
        }

        // Class not found
        throw new InvalidArgumentException(
            "Condition class '{$className}' not found. " .
            "Looked in: {$projectClass}, {$vendorClass}. " .
            "Create the class with __invoke(): bool method."
        );
    }
}
