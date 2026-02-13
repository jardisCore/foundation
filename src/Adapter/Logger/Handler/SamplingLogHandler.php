<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Handler\LogSampling;

/**
 * Sampling Log Handler
 *
 * Creates sampling log handler from configuration.
 * Wraps another handler and samples log messages based on configured strategy.
 *
 * Required configuration:
 * - 'wraps': Name of the handler to wrap (set via LOG_HANDLER{N}_WRAPS)
 *
 * Sampling Strategies:
 * - 'percentage': Randomly sample X% of logs (default: 10%)
 * - 'rate': Log first N messages per second
 * - 'smart': Always log ERROR+, sample INFO/DEBUG
 * - 'fingerprint': Deduplicate identical logs within time window
 *
 * Example .env configuration:
 * ```
 * # Percentage Strategy (default)
 * LOG_HANDLER1_TYPE=file
 * LOG_HANDLER1_NAME=debug_file
 * LOG_HANDLER1_PATH=/var/log/debug.log
 *
 * LOG_HANDLER2_TYPE=sampling
 * LOG_HANDLER2_WRAPS=debug_file
 * LOG_HANDLER2_STRATEGY=percentage
 * LOG_HANDLER2_PERCENTAGE=10
 *
 * # Rate Strategy
 * LOG_HANDLER3_TYPE=sampling
 * LOG_HANDLER3_WRAPS=debug_file
 * LOG_HANDLER3_STRATEGY=rate
 * LOG_HANDLER3_RATE=100
 *
 * # Smart Strategy
 * LOG_HANDLER4_TYPE=sampling
 * LOG_HANDLER4_WRAPS=debug_file
 * LOG_HANDLER4_STRATEGY=smart
 * LOG_HANDLER4_SAMPLE_PERCENTAGE=10
 *
 * # Fingerprint Strategy
 * LOG_HANDLER5_TYPE=sampling
 * LOG_HANDLER5_WRAPS=debug_file
 * LOG_HANDLER5_STRATEGY=fingerprint
 * LOG_HANDLER5_WINDOW=60
 * ```
 */
class SamplingLogHandler
{
    /**
     * Create sampling handler that wraps another handler.
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
        // Get sampling strategy (percentage, rate, smart, fingerprint)
        $strategy = $config->getOption('strategy', 'percentage');
        if (!is_string($strategy)) {
            $strategy = 'percentage';
        }
        $strategy = strtolower(trim($strategy));

        // Build config array based on strategy
        $samplingConfig = match ($strategy) {
            'percentage' => $this->buildPercentageConfig($config),
            'rate' => $this->buildRateConfig($config),
            'smart' => $this->buildSmartConfig($config),
            'fingerprint' => $this->buildFingerprintConfig($config),
            default => throw new InvalidArgumentException(
                "Invalid sampling strategy: '{$strategy}'. Valid: percentage, rate, smart, fingerprint"
            ),
        };

        return new LogSampling($wrappedHandler, $strategy, $samplingConfig);
    }

    /**
     * Build config for percentage strategy.
     *
     * @param LoggerHandlerConfig $config
     * @return array<string, mixed>
     */
    private function buildPercentageConfig(LoggerHandlerConfig $config): array
    {
        $percentage = $config->getOption('percentage', 10);
        if (is_string($percentage)) {
            $percentage = (int) $percentage;
        }
        if (!is_int($percentage) || $percentage < 1 || $percentage > 100) {
            $percentage = 10;
        }

        return ['percentage' => $percentage];
    }

    /**
     * Build config for rate strategy.
     *
     * @param LoggerHandlerConfig $config
     * @return array<string, mixed>
     */
    private function buildRateConfig(LoggerHandlerConfig $config): array
    {
        $rate = $config->getOption('rate', 100);
        if (is_string($rate)) {
            $rate = (int) $rate;
        }
        if (!is_int($rate) || $rate < 1) {
            $rate = 100;
        }

        return ['rate' => $rate];
    }

    /**
     * Build config for smart strategy.
     *
     * @param LoggerHandlerConfig $config
     * @return array<string, mixed>
     */
    private function buildSmartConfig(LoggerHandlerConfig $config): array
    {
        $samplePercentage = $config->getOption('sample_percentage', 10);
        if (is_string($samplePercentage)) {
            $samplePercentage = (int) $samplePercentage;
        }
        if (!is_int($samplePercentage) || $samplePercentage < 1 || $samplePercentage > 100) {
            $samplePercentage = 10;
        }

        return [
            'alwaysLogLevels' => ['error', 'critical', 'alert', 'emergency'],
            'samplePercentage' => $samplePercentage,
        ];
    }

    /**
     * Build config for fingerprint strategy.
     *
     * @param LoggerHandlerConfig $config
     * @return array<string, mixed>
     */
    private function buildFingerprintConfig(LoggerHandlerConfig $config): array
    {
        $window = $config->getOption('window', 60);
        if (is_string($window)) {
            $window = (int) $window;
        }
        if (!is_int($window) || $window < 1) {
            $window = 60;
        }

        return ['window' => $window];
    }
}
