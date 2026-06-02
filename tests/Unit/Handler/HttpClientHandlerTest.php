<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Handler;

use JardisAdapter\Http\HttpClient;
use JardisCore\Foundation\Handler\HttpClientHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

/**
 * Unit tests for HttpClientHandler.
 */
class HttpClientHandlerTest extends TestCase
{
    public function testReturnsHttpClient(): void
    {
        $handler = new HttpClientHandler();
        $result = $handler($this->env([]));

        self::assertInstanceOf(ClientInterface::class, $result);
        self::assertInstanceOf(HttpClient::class, $result);
    }

    public function testReadsEnvValues(): void
    {
        $handler = new HttpClientHandler();
        $result = $handler($this->env([
            'http_base_url' => 'https://api.example.com',
            'http_timeout' => '60',
            'http_connect_timeout' => '5',
            'http_verify_ssl' => 'false',
            'http_bearer_token' => 'test-token',
            'http_max_retries' => '3',
            'http_retry_delay_ms' => '200',
        ]));

        self::assertInstanceOf(ClientInterface::class, $result);
    }

    public function testBasicAuthFromEnv(): void
    {
        $handler = new HttpClientHandler();
        $result = $handler($this->env([
            'http_basic_user' => 'admin',
            'http_basic_password' => 'secret',
        ]));

        self::assertInstanceOf(ClientInterface::class, $result);
    }

    /**
     * @param array<string, mixed> $data
     * @return \Closure(string): mixed
     */
    private function env(array $data): \Closure
    {
        return static fn (string $key): mixed => $data[strtolower($key)] ?? null;
    }
}
