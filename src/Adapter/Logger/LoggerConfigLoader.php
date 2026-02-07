<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger;

use InvalidArgumentException;
use JardisPsr\Foundation\DomainKernelInterface;

/**
 * Loads logger handler configurations from flat environment variables.
 *
 * Reads LOG_HANDLER{N}_* environment variables and converts them
 * to typed LoggerHandlerConfig objects.
 *
 * Environment variable pattern:
 * - LOG_HANDLER1_TYPE=file
 * - LOG_HANDLER1_NAME=app_log (optional)
 * - LOG_HANDLER1_LEVEL=INFO (optional, defaults to LOG_DEFAULT_LEVEL)
 * - LOG_HANDLER1_FORMAT=json (optional, defaults to LOG_DEFAULT_FORMAT)
 * - LOG_HANDLER1_PATH=/tmp/app.log (handler-specific)
 * - LOG_HANDLER1_ALWAYS=false (optional)
 * - LOG_HANDLER2_TYPE=slack
 * - LOG_HANDLER2_WEBHOOK=https://...
 * etc.
 *
 * Default values from LOG_DEFAULT_* environment variables:
 * - LOG_DEFAULT_ALWAYS: Default for 'always' parameter (default: false)
 * - LOG_DEFAULT_FORMAT: Default format (default: json)
 * - LOG_DEFAULT_LEVEL: Default log level (default: INFO)
 */
class LoggerConfigLoader
{
    /**
     * Load logger handler configurations from flat environment variables.
     *
     * Scans for LOG_HANDLER{N}_TYPE where N starts at 1 and increments.
     * Stops when no TYPE is found for the next index.
     *
     * @param DomainKernelInterface $kernel Domain kernel with environment access
     * @return array<int, LoggerHandlerConfig> Array of handler configurations
     * @throws InvalidArgumentException If handler configuration is invalid
     */
    public function load(DomainKernelInterface $kernel): array
    {
        // Read defaults from environment
        $defaults = $this->loadDefaults($kernel);

        $configs = [];
        $index = 1;

        // Scan for LOG_HANDLER{N}_TYPE until we don't find one
        while (true) {
            $type = $kernel->getEnv("LOG_HANDLER{$index}_TYPE");

            if ($type === null || (is_string($type) && trim($type) === '')) {
                break; // No more handlers
            }

            try {
                // Build handler configuration from flat ENV variables
                $handlerConfig = $this->loadHandlerConfig($kernel, $index);
                $configs[] = LoggerHandlerConfig::fromArray($handlerConfig, $defaults);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException(
                    "Invalid configuration in LOG_HANDLER{$index}: " . $e->getMessage(),
                    0,
                    $e
                );
            }

            $index++;
        }

        return $configs;
    }

    /**
     * Load configuration for a single handler from flat ENV variables.
     *
     * Reads all LOG_HANDLER{N}_* variables and builds an array.
     *
     * @param DomainKernelInterface $kernel
     * @param int $index Handler index (1, 2, 3, ...)
     * @return array<string, mixed>
     */
    private function loadHandlerConfig(DomainKernelInterface $kernel, int $index): array
    {
        $prefix = "LOG_HANDLER{$index}_";
        $config = [];

        // Get all environment variables for this handler
        // getEnv(null) returns all environment variables as array
        $allEnv = $kernel->getEnv(null);

        if (!is_array($allEnv)) {
            return $config;
        }

        foreach ($allEnv as $key => $value) {
            if (str_starts_with((string) $key, $prefix)) {
                // Extract parameter name (e.g., LOG_HANDLER1_TYPE -> type)
                $paramName = strtolower(substr((string) $key, strlen($prefix)));
                $config[$paramName] = $value;
            }
        }

        return $config;
    }

    /**
     * Load default values from environment variables.
     *
     * Reads LOG_DEFAULT_* environment variables and returns them as array.
     *
     * @param DomainKernelInterface $kernel
     * @return array<string, mixed>
     */
    private function loadDefaults(DomainKernelInterface $kernel): array
    {
        $always = $kernel->getEnv('LOG_DEFAULT_ALWAYS');
        if (is_string($always)) {
            $always = in_array(strtolower($always), ['true', '1', 'yes', 'on'], true);
        }

        return [
            'always' => $always ?? false,
            'format' => $kernel->getEnv('LOG_DEFAULT_FORMAT') ?? 'json',
            'level' => $kernel->getEnv('LOG_DEFAULT_LEVEL') ?? 'INFO',
        ];
    }
}
