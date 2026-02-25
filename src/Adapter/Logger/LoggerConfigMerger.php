<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger;

/**
 * Merges and deduplicates logger handler configurations.
 *
 * Implements the deduplication logic based on handler type and core parameters:
 *
 * Rules:
 * 1. If 'always=true': Always create new handler (no deduplication)
 * 2. If type+name+format+level identical: Keep only one (duplicate)
 * 3. If type+name+format identical but level differs: Use more verbose level
 * 4. Otherwise: Both handlers are valid (different configurations)
 *
 * "BC is King" principle: Later configurations override earlier ones.
 * Order: Foundation → Domain → BoundedContext
 */
class LoggerConfigMerger
{
    /**
     * Handler-type specific deduplication keys.
     *
     * Defines which parameters are relevant for deduplication per handler type.
     * - file: Uses format (json vs text matters)
     * - Others: No format (slack, loki, database don't have format concept)
     *
     * @var array<string, array<int, string>>
     */
    private array $deduplicationKeys = [
        'file' => ['type', 'name', 'format', 'level'],
        'slack' => ['type', 'name', 'level'],
        'loki' => ['type', 'name', 'level'],
        'database' => ['type', 'name', 'level'],
        'teams' => ['type', 'name', 'level'],
        'redis' => ['type', 'name', 'level'],
        'rabbitmq' => ['type', 'name', 'level'],
        'kafka' => ['type', 'name', 'level'],
        'syslog' => ['type', 'name', 'level'],
        'errorlog' => ['type', 'name', 'level'],
        'console' => ['type', 'name', 'level'],
        'webhook' => ['type', 'name', 'level'],
        'email' => ['type', 'name', 'level'],
        'stash' => ['type', 'name', 'level'],
        'null' => ['type', 'name', 'level'],
    ];

    /**
     * Log level hierarchy (from most verbose to least verbose).
     *
     * Lower index = more verbose (logs more)
     * DEBUG logs everything, EMERGENCY logs only emergency
     *
     * @var array<int, string>
     */
    private array $levelHierarchy = [
        'DEBUG',
        'INFO',
        'NOTICE',
        'WARNING',
        'ERROR',
        'CRITICAL',
        'ALERT',
        'EMERGENCY',
    ];

    /**
     * Merge and deduplicate handler configurations.
     *
     * @param array<int, LoggerHandlerConfig> $configs Handler configurations
     * @return array<int, LoggerHandlerConfig> Merged and deduplicated configurations
     */
    public function merge(array $configs): array
    {
        $merged = [];
        $registry = [];

        foreach ($configs as $config) {
            // Rule 1: always=true → Always add, skip deduplication
            if ($config->isAlways()) {
                $merged[] = $config;
                continue;
            }

            // Generate unique key for this handler
            $uniqueKey = $config->getUniqueKey($this->deduplicationKeys);

            // Generate key without level for level-merge detection
            $baseKey = $this->getBaseKey($config);

            // Check if we've seen this exact configuration
            if (isset($registry[$uniqueKey])) {
                // Rule 2: Exact duplicate (type+name+format+level identical)
                // "BC is King": Later config replaces earlier one
                $registry[$uniqueKey] = $config;
                $registry[$baseKey] = $config;
                continue;
            }

            // Check if we have same handler but different level
            if (isset($registry[$baseKey])) {
                // Rule 3: Same handler, different level → Use more verbose level
                /** @var LoggerHandlerConfig $existing */
                $existing = $registry[$baseKey];
                $moreVerboseLevel = $this->getMostVerboseLevel($existing->level, $config->level);

                // Create new config with more verbose level but other params from later config (BC is King)
                $mergedConfig = new LoggerHandlerConfig(
                    type: $config->type,
                    name: $config->name,
                    format: $config->format,
                    level: $moreVerboseLevel,
                    always: $config->always,
                    options: $config->options,
                );

                // Remove old entries
                unset($registry[$baseKey]);
                if (isset($registry[$this->getUniqueKeyForConfig($existing)])) {
                    unset($registry[$this->getUniqueKeyForConfig($existing)]);
                }

                // Register with both keys
                $newUniqueKey = $mergedConfig->getUniqueKey($this->deduplicationKeys);
                $newBaseKey = $this->getBaseKey($mergedConfig);
                $registry[$newUniqueKey] = $mergedConfig;
                $registry[$newBaseKey] = $mergedConfig;
                continue;
            }

            // Rule 4: New handler (different type/name/format)
            $registry[$uniqueKey] = $config;
            $registry[$baseKey] = $config;
        }

        // Collect unique configs (we stored each config under multiple keys)
        $seen = [];
        foreach ($registry as $config) {
            $hash = spl_object_hash($config);
            if (!isset($seen[$hash])) {
                $merged[] = $config;
                $seen[$hash] = true;
            }
        }

        return $merged;
    }

    /**
     * Get base key without level (for level-merge detection).
     *
     * @param LoggerHandlerConfig $config
     * @return string
     */
    private function getBaseKey(LoggerHandlerConfig $config): string
    {
        $keys = $this->deduplicationKeys[$config->type] ?? ['type', 'name', 'level'];
        $keysWithoutLevel = array_filter($keys, fn($key) => $key !== 'level');

        $values = [];
        foreach ($keysWithoutLevel as $key) {
            $values[] = match ($key) {
                'type' => $config->type,
                'name' => $config->name ?? '',
                'format' => $config->format ?? '',
                default => '',
            };
        }

        return 'base_' . implode('|', $values);
    }

    /**
     * Get unique key for a config (helper for cleanup).
     *
     * @param LoggerHandlerConfig $config
     * @return string
     */
    private function getUniqueKeyForConfig(LoggerHandlerConfig $config): string
    {
        return $config->getUniqueKey($this->deduplicationKeys);
    }

    /**
     * Get the most verbose (lowest) log level from two levels.
     *
     * DEBUG > INFO > WARNING > ERROR (DEBUG logs the most)
     *
     * @param string $level1 First log level
     * @param string $level2 Second log level
     * @return string Most verbose level
     */
    private function getMostVerboseLevel(string $level1, string $level2): string
    {
        $index1 = array_search(strtoupper($level1), $this->levelHierarchy, true);
        $index2 = array_search(strtoupper($level2), $this->levelHierarchy, true);

        // If level not found in hierarchy, default to INFO
        if ($index1 === false) {
            $index1 = array_search('INFO', $this->levelHierarchy, true);
        }
        if ($index2 === false) {
            $index2 = array_search('INFO', $this->levelHierarchy, true);
        }

        // Lower index = more verbose
        return $index1 < $index2 ? $level1 : $level2;
    }
}
