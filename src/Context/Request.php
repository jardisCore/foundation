<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Context;

/**
 * Represents an immutable request object encapsulating client, user, version, and payload details.
 */
readonly class Request
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public mixed $clientId,
        public mixed $userId,
        public mixed $version,
        public array $payload
    ) {
    }

    /**
     * Get value from the payload with optional type casting.
     *
     * @param string $key The key in payload
     * @return mixed The value (optionally type-casted), or null if the key missing or cast fails
     */
    protected function get(string $key): mixed
    {
        return $this->payload[$key] ?? null;
    }

    /**
     * Check if the payload contains a key.
     */
    protected function has(string $key): bool
    {
        return array_key_exists($key, $this->payload);
    }
}
