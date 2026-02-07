<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger;

use Exception;
use JardisAdapter\Logger\Logger;
use JardisPsr\Foundation\DomainKernelInterface;
use Psr\Log\LoggerInterface;

/**
 * Initialize Logger Service
 *
 * Creates and configures logger with handlers defined via flat LOG_HANDLER{N}_* environment variables.
 *
 * Configuration via .env:
 * ```env
 * # Default values
 * LOG_DEFAULT_ALWAYS=false
 * LOG_DEFAULT_FORMAT=json
 * LOG_DEFAULT_LEVEL=INFO
 *
 * # Handler 1: File Logger
 * LOG_HANDLER1_TYPE=file
 * LOG_HANDLER1_NAME=app_log
 * LOG_HANDLER1_LEVEL=INFO
 * LOG_HANDLER1_PATH=/var/log/app.log
 *
 * # Handler 2: Slack Alerts
 * LOG_HANDLER2_TYPE=slack
 * LOG_HANDLER2_NAME=alerts
 * LOG_HANDLER2_LEVEL=ERROR
 * LOG_HANDLER2_WEBHOOK=https://...
 *
 * # Handler 3: Loki
 * LOG_HANDLER3_TYPE=loki
 * LOG_HANDLER3_LEVEL=WARNING
 * LOG_HANDLER3_URL=http://loki:3100
 *
 * # Handler 4: Sampling Wrapper (wraps Handler 1)
 * LOG_HANDLER4_TYPE=sampling
 * LOG_HANDLER4_NAME=sampled_file
 * LOG_HANDLER4_WRAPS=app_log
 * LOG_HANDLER4_RATE=0.1
 * LOG_HANDLER4_STRATEGY=random
 *
 * # Handler 5: FingersCrossed Wrapper (wraps Handler 2)
 * LOG_HANDLER5_TYPE=fingerscrossed
 * LOG_HANDLER5_NAME=buffered_slack
 * LOG_HANDLER5_WRAPS=alerts
 * LOG_HANDLER5_ACTIVATION_LEVEL=ERROR
 *
 * # Handler 6: Conditional Router (wraps multiple handlers)
 * LOG_HANDLER6_TYPE=conditional
 * LOG_HANDLER6_WRAPS=app_log,alerts
 * LOG_HANDLER6_CONDITIONS=is_production,is_vip_user
 * ```
 *
 * Cascade loading (BC is King):
 * - Foundation/.env (base defaults)
 * - Domain/.env (domain-specific, overrides Foundation)
 * - BoundedContext/.env (BC-specific, overrides Domain)
 *
 * Handler deduplication rules:
 * 1. always=true → Always create new handler (skip deduplication)
 * 2. type+name+format+level identical → Keep only one (duplicate)
 * 3. type+name+format identical but level differs → Use more verbose level
 * 4. Otherwise → Both handlers are valid
 *
 * Supported handler types:
 * - file: File-based logging (requires: path)
 * - slack: Slack webhook (requires: webhook)
 * - loki: Grafana Loki (requires: url)
 * - database: Database table logging (requires: active DB connection, optional: table)
 * - teams: Microsoft Teams webhook (requires: webhook)
 * - redis: Redis storage (requires: key, optional: host, port)
 * - syslog: System log (optional: facility)
 * - errorlog: PHP error_log()
 * - console: Console/STDOUT output
 * - webhook: Custom webhook (requires: url)
 *
 * Wrapper handler types (require 'wraps' parameter):
 * - sampling: Samples a percentage of log messages (requires: wraps, optional: rate, strategy)
 * - fingerscrossed: Buffers logs until activation level (requires: wraps, optional: activation_level)
 * - conditional: Routes logs based on conditions (requires: wraps, conditions)
 */
class InitLogger
{
    private LoggerConfigLoader $loader;
    private LoggerConfigMerger $merger;
    private LoggerHandlerFactory $factory;

    public function __construct(
        ?LoggerConfigLoader $loader = null,
        ?LoggerConfigMerger $merger = null,
        ?LoggerHandlerFactory $factory = null
    ) {
        $this->loader = $loader ?? new LoggerConfigLoader();
        $this->merger = $merger ?? new LoggerConfigMerger();
        $this->factory = $factory ?? new LoggerHandlerFactory();
    }

    /**
     * Initialize logger from .env configuration.
     *
     * @param DomainKernelInterface $kernel Domain kernel with environment access
     * @return LoggerInterface|null Logger instance with configured handlers, or null if no handlers configured
     * @throws Exception If configuration is invalid or handler creation fails
     */
    public function __invoke(DomainKernelInterface $kernel): ?LoggerInterface
    {
        try {
            // 1. Load handler configurations from environment
            $configs = $this->loader->load($kernel);

            if (empty($configs)) {
                return null; // No handlers configured
            }

            // 2. Merge and deduplicate configurations
            $mergedConfigs = $this->merger->merge($configs);

            if (empty($mergedConfigs)) {
                return null; // All handlers were deduplicated away
            }

            // 3. Create logger instance
            $context = $kernel->getEnv('APP_ENV') ?? 'app';
            $logger = new Logger((string) $context);

            // 4. Create and register all handlers
            foreach ($mergedConfigs as $config) {
                try {
                    // Pass all configs to factory for wrapper handler resolution
                    $handler = $this->factory->create($config, $kernel, $mergedConfigs);
                    $logger->addHandler($handler);
                } catch (Exception $e) {
                    // Re-throw with context about which handler failed
                    throw new Exception(
                        "Failed to create log handler '{$config->type}'" .
                        ($config->name ? " (name: {$config->name})" : '') .
                        ": {$e->getMessage()}",
                        0,
                        $e
                    );
                }
            }

            return $logger;
        } catch (Exception $e) {
            // If logger initialization fails, we can't log the error
            // Re-throw with clear context
            throw new Exception(
                "Logger initialization failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
