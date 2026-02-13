<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Context;

use JardisPsr\Foundation\ResponseInterface;

/**
 * Context Response for collecting results across bounded context calls.
 *
 * Supports nested responses for BC chains:
 * Sales BC → Inventory BC → Warehouse BC
 *
 * Each response can collect:
 * - Data (results)
 * - Events (domain events)
 * - Errors (validation/business errors)
 * - Sub-Responses (nested BC calls)
 *
 * Mutable accumulator object (opposite of readonly DomainRequest).
 */
class Response implements ResponseInterface
{
    /** @var array<string, mixed> */
    private array $data = [];
    /** @var array<int, object> */
    private array $events = [];
    /** @var array<int, string> */
    private array $errors = [];
    /** @var array<int, ResponseInterface> */
    private array $responses = [];
    private readonly float $createdAt;

    public function __construct(
        private readonly string $context
    ) {
        $this->createdAt = microtime(true);
    }

    /**
     * Get the context (BC/Use-Case name) of this response.
     */
    public function getContext(): string
    {
        return $this->context;
    }

    /**
     * Add a single data entry.
     */
    public function addData(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Set all data at once (replaces existing data).
     * @param array<string, mixed> $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get data from this response only (non-recursive).
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get all data including sub-responses (recursive).
     * Returns nested array with context as keys.
     * @return array<string, array<string, mixed>>
     */
    public function getAllData(): array
    {
        $allData = [$this->context => $this->data];

        foreach ($this->responses as $response) {
            $allData = array_merge($allData, $response->getAllData());
        }

        return $allData;
    }

    /**
     * Add a domain event.
     */
    public function addEvent(object $event): self
    {
        $this->events[] = $event;

        return $this;
    }

    /**
     * Get events from this response only (non-recursive).
     * @return array<int, object>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Get all events including sub-responses (recursive).
     * Returns a flat array of all events.
     * @return array<int, object>
     */
    public function getAllEvents(): array
    {
        $allEvents = $this->events;

        foreach ($this->responses as $response) {
            $allEvents = array_merge($allEvents, $response->getAllEvents());
        }

        return $allEvents;
    }

    /**
     * Add an error message.
     */
    public function addError(string $message): self
    {
        $this->errors[] = $message;

        return $this;
    }

    /**
     * Get errors from this response only (non-recursive).
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all errors, including sub-responses (recursive).
     * Returns array with context as keys for traceability.
     * @return array<string, array<int, string>>
     */
    public function getAllErrors(): array
    {
        $allErrors = [];

        if (!empty($this->errors)) {
            $allErrors[$this->context] = $this->errors;
        }

        foreach ($this->responses as $response) {
            $subErrors = $response->getAllErrors();
            if (!empty($subErrors)) {
                $allErrors = array_merge($allErrors, $subErrors);
            }
        }

        return $allErrors;
    }

    /**
     * Check if this response has errors (non-recursive).
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Add a sub-response from a nested BC call.
     */
    public function addResponse(ResponseInterface $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    /**
     * Get direct sub-responses (non-recursive).
     * @return array<int, ResponseInterface>
     */
    public function getResponses(): array
    {
        return $this->responses;
    }

    /**
     * Check if this response and all sub-responses are successful (no errors).
     */
    public function isSuccess(): bool
    {
        if ($this->hasErrors()) {
            return false;
        }

        foreach ($this->responses as $response) {
            if (!$response->isSuccess()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get metadata summary for this response (non-recursive).
     *
     * Returns array with:
     * - context: BC/Use-Case name
     * - createdAt: Unix timestamp with microseconds
     * - elapsedTime: Seconds since creation
     * - eventCount: Number of events collected
     * - errorCount: Number of errors collected
     * - responseCount: Number of sub-responses
     * - hasErrors: Boolean indicating if errors exist
     * - isSuccess: Boolean indicating success status
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'context' => $this->context,
            'createdAt' => $this->createdAt,
            'elapsedTime' => microtime(true) - $this->createdAt,
            'eventCount' => count($this->events),
            'errorCount' => count($this->errors),
            'responseCount' => count($this->responses),
            'hasErrors' => $this->hasErrors(),
            'isSuccess' => $this->isSuccess(),
        ];
    }

    /**
     * Get metadata for all responses, including sub-responses (recursive).
     *
     * Returns nested array with metadata for each response in the chain.
     * @return array<string, array<string, mixed>>
     */
    public function getAllMetadata(): array
    {
        $metadata = [$this->context => $this->getMetadata()];

        foreach ($this->responses as $response) {
            $metadata = array_merge($metadata, $response->getAllMetadata());
        }

        return $metadata;
    }
}
