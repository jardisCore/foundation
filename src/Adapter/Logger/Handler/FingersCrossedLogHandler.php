<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Handler\LogFingersCrossed;

/**
 * Fingers Crossed Log Handler
 *
 * Creates fingerscrossed log handler from configuration.
 * Buffers log messages until activation level is reached, then flushes all buffered messages.
 *
 * Required configuration:
 * - 'wraps': Name of the handler to wrap (set via LOG_HANDLER{N}_WRAPS)
 *
 * Optional options:
 * - 'activation_level' (string, default: 'ERROR') - Level that triggers flush
 * - 'buffer_size' (int, default: 0 = unlimited) - Maximum buffer size
 * - 'stop_buffering' (bool, default: true) - Stop buffering after activation
 *
 * Example .env configuration:
 * ```
 * LOG_HANDLER1_TYPE=slack
 * LOG_HANDLER1_NAME=slack_alerts
 * LOG_HANDLER1_WEBHOOK=https://hooks.slack.com/services/xxx
 *
 * LOG_HANDLER2_TYPE=fingerscrossed
 * LOG_HANDLER2_WRAPS=slack_alerts
 * LOG_HANDLER2_ACTIVATION_LEVEL=ERROR
 * LOG_HANDLER2_BUFFER_SIZE=100
 * LOG_HANDLER2_STOP_BUFFERING=true
 * ```
 *
 * Use Case: Save costs by only sending logs when something goes wrong,
 *           but include all previous context (DEBUG, INFO, WARNING) for debugging.
 */
class FingersCrossedLogHandler
{
    /**
     * Create fingerscrossed handler that wraps another handler.
     *
     * @param LoggerHandlerConfig $config Handler configuration
     * @param StreamableLogCommandInterface $wrappedHandler The handler to wrap (injected by factory)
     * @return LogCommandInterface
     * @throws InvalidArgumentException If configuration is invalid
     */
    public function __invoke(
        LoggerHandlerConfig $config,
        StreamableLogCommandInterface $wrappedHandler
    ): LogCommandInterface {
        // Get activation level (level that triggers the flush)
        $activationLevel = $config->getOption('activation_level', 'ERROR');
        if (!is_string($activationLevel)) {
            $activationLevel = 'ERROR';
        }
        $activationLevel = strtoupper(trim($activationLevel));

        // Get buffer size (default 100)
        $bufferSize = $config->getOption('buffer_size', 100);
        if (is_string($bufferSize)) {
            $bufferSize = (int) $bufferSize;
        }
        if (!is_int($bufferSize) || $bufferSize < 1) {
            $bufferSize = 100;
        }

        // Get stop buffering flag
        $stopBuffering = $config->getOption('stop_buffering', true);
        if (is_string($stopBuffering)) {
            $stopBuffering = in_array(strtolower($stopBuffering), ['true', '1', 'yes', 'on'], true);
        }
        if (!is_bool($stopBuffering)) {
            $stopBuffering = true;
        }

        return new LogFingersCrossed(
            $wrappedHandler,
            $activationLevel,
            $bufferSize,
            $stopBuffering
        );
    }
}
