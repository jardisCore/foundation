<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger;

use InvalidArgumentException;

/**
 * Configuration DTO for a single log handler.
 *
 * Represents a typed, immutable configuration object for log handlers.
 * Can be constructed from array configuration (parsed from .env by DotEnv).
 *
 * Core parameters (used for deduplication):
 * - type: Handler type (file, slack, loki, database, etc.)
 * - name: Optional identifier for handler
 * - format: Optional format (json, text) - only relevant for some handlers
 * - level: Minimum log level (DEBUG, INFO, WARNING, ERROR, etc.)
 * - always: Force new handler (skip deduplication)
 *
 * Additional parameters are stored in $options (handler-specific like path, webhook, url, etc.)
 */
readonly class LoggerHandlerConfig
{
    /**
     * @param string $type Handler type (file, slack, loki, etc.) - REQUIRED
     * @param string|null $name Optional handler name for identification/deduplication
     * @param string|null $format Optional format (json, text, custom) - not all handlers use this
     * @param string $level Minimum log level (DEBUG, INFO, WARNING, ERROR, etc.)
     * @param bool $always Force new handler (skip deduplication logic)
     * @param array<string, mixed> $options Handler-specific options (path, webhook, url, table, etc.)
     */
    public function __construct(
        public string $type,
        public ?string $name = null,
        public ?string $format = null,
        public string $level = 'INFO',
        public bool $always = false,
        public array $options = [],
    ) {
    }

    /**
     * Create configuration from array (parsed from .env).
     *
     * Required: 'type'
     * Optional: 'name', 'format', 'level', 'always'
     * Everything else goes into 'options'
     *
     * @param array<string, mixed> $config Configuration array
     * @param array<string, mixed> $defaults Default values from LOG_DEFAULT_* env vars
     * @return self
     * @throws InvalidArgumentException If required 'type' is missing
     */
    public static function fromArray(array $config, array $defaults = []): self
    {
        if (!isset($config['type']) || !is_string($config['type']) || trim($config['type']) === '') {
            throw new InvalidArgumentException('Handler configuration requires "type" parameter');
        }

        // Extract core parameters
        $type = trim($config['type']);
        $name = isset($config['name']) && is_string($config['name']) ? trim($config['name']) : null;
        $format = $config['format'] ?? $defaults['format'] ?? null;
        $level = $config['level'] ?? $defaults['level'] ?? 'INFO';
        $always = $config['always'] ?? $defaults['always'] ?? false;

        // Cast always to bool if string
        if (is_string($always)) {
            $always = in_array(strtolower($always), ['true', '1', 'yes', 'on'], true);
        }

        // Everything else goes into options (handler-specific parameters)
        $options = array_diff_key(
            $config,
            array_flip(['type', 'name', 'format', 'level', 'always'])
        );

        return new self(
            type: $type,
            name: $name !== '' ? $name : null,
            format: is_string($format) && $format !== '' ? $format : null,
            level: is_string($level) ? strtoupper(trim($level)) : 'INFO',
            always: (bool) $always,
            options: $options,
        );
    }

    /**
     * Get a specific option value.
     *
     * @param string $key Option key
     * @param mixed $default Default value if option not found
     * @return mixed
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Check if this handler should always be created (skip deduplication).
     */
    public function isAlways(): bool
    {
        return $this->always;
    }

    /**
     * Convert configuration to array (for debugging/logging).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'format' => $this->format,
            'level' => $this->level,
            'always' => $this->always,
            'options' => $this->options,
        ];
    }

    /**
     * Generate unique key for deduplication based on handler type.
     *
     * Different handler types use different parameters for deduplication:
     * - file: type, name, format, level
     * - slack, loki, database, etc.: type, name, level (no format)
     *
     * @param array<string, array<int, string>> $deduplicationKeys Handler-specific keys
     * @return string
     */
    public function getUniqueKey(array $deduplicationKeys = []): string
    {
        // Get keys for this handler type, or use default
        $keys = $deduplicationKeys[$this->type] ?? ['type', 'name', 'level'];

        $values = [];
        foreach ($keys as $key) {
            $values[] = match ($key) {
                'type' => $this->type,
                'name' => $this->name ?? '',
                'format' => $this->format ?? '',
                'level' => $this->level,
                default => '',
            };
        }

        return implode('|', $values);
    }
}
