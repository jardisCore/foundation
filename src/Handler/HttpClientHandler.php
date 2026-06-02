<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Handler;

use Closure;
use JardisAdapter\Http\Config\ClientConfig;
use JardisAdapter\Http\HttpClient;
use JardisAdapter\Http\Message\Psr17Factory;
use Psr\Http\Client\ClientInterface;

/**
 * Builds a PSR-18 HTTP client from ENV values.
 *
 * Requires jardisadapter/http. Configuration via HTTP_* environment variables.
 */
final class HttpClientHandler
{
    /** @param Closure(string): mixed $env */
    public function __invoke(Closure $env): ?ClientInterface
    {
        if (!class_exists(HttpClient::class)) {
            return null;
        }

        $psr17 = new Psr17Factory();

        return new HttpClient(
            $psr17,
            $psr17,
            $psr17,
            $psr17,
            new ClientConfig(
                timeout: (int) ($env('http_timeout') ?? 30),
                connectTimeout: (int) ($env('http_connect_timeout') ?? 10),
                baseUrl: $env('http_base_url') !== null ? (string) $env('http_base_url') : null,
                verifySsl: ($env('http_verify_ssl') ?? 'true') === 'true',
                bearerToken: $env('http_bearer_token') !== null
                    ? (string) $env('http_bearer_token') : null,
                basicUser: $env('http_basic_user') !== null
                    ? (string) $env('http_basic_user') : null,
                basicPassword: $env('http_basic_password') !== null
                    ? (string) $env('http_basic_password') : null,
                maxRetries: (int) ($env('http_max_retries') ?? 0),
                retryDelayMs: (int) ($env('http_retry_delay_ms') ?? 100),
            ),
        );
    }
}
