<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Integration;

use JardisCore\Foundation\Handler\LoggerHandler;
use JardisCore\Foundation\Handler\RedisHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Redis;

/**
 * Integration tests for LoggerHandler with real services.
 */
class LoggerHandlerTest extends TestCase
{
    public function testFileHandler(): void
    {
        $logFile = '/tmp/integration_test_' . uniqid() . '.log';

        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'file:INFO',
            'log_context' => 'test',
            'log_file_path' => $logFile,
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);

        $logger->info('Integration test message');

        self::assertFileExists($logFile);
        self::assertStringContainsString('Integration test message', file_get_contents($logFile));

        @unlink($logFile);
    }

    public function testConsoleHandler(): void
    {
        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'console:INFO',
            'log_context' => 'test',
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);

        ob_start();
        $logger->info('Console test');
        ob_end_clean();
    }

    public function testErrorLogHandler(): void
    {
        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'errorlog:WARNING',
            'log_context' => 'test',
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testSyslogHandler(): void
    {
        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'syslog:ERROR',
            'log_context' => 'test',
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testNullHandler(): void
    {
        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'null',
            'log_context' => 'test',
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testMultipleHandlers(): void
    {
        $logFile = '/tmp/integration_test_' . uniqid() . '.log';

        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'file:INFO,console:WARNING,null:DEBUG',
            'log_context' => 'test',
            'log_file_path' => $logFile,
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);

        $logger->info('Multi handler test');

        self::assertFileExists($logFile);
        self::assertStringContainsString('Multi handler test', file_get_contents($logFile));

        @unlink($logFile);
    }

    public function testRedisHandler(): void
    {
        $redis = $this->buildRedis();

        $handler = new LoggerHandler();
        $logger = $handler(
            $this->env([
                'log_handlers' => 'redis:INFO',
                'log_context' => 'test',
            ]),
            $redis,
        );

        self::assertInstanceOf(LoggerInterface::class, $logger);

        $logger->info('Redis log test');
    }

    public function testRedisSkippedWithoutInstance(): void
    {
        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'redis:INFO',
            'log_context' => 'test',
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testNoHandlersReturnsNull(): void
    {
        $handler = new LoggerHandler();
        $result = $handler($this->env([]));

        self::assertNull($result);
    }

    public function testLevelFallbackToDefault(): void
    {
        $logFile = '/tmp/integration_test_' . uniqid() . '.log';

        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'file',
            'log_level' => 'DEBUG',
            'log_context' => 'test',
            'log_file_path' => $logFile,
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);

        $logger->debug('Debug fallback test');

        self::assertFileExists($logFile);
        self::assertStringContainsString('Debug fallback test', file_get_contents($logFile));

        @unlink($logFile);
    }

    public function testInvalidHandlerIsIgnored(): void
    {
        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'nonexistent:INFO,null:DEBUG',
            'log_context' => 'test',
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testSlackWithUrlCreatesLogger(): void
    {
        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'slack:ERROR',
            'log_context' => 'test',
            'log_slack_url' => 'https://hooks.slack.com/test',
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testTeamsWithUrlCreatesLogger(): void
    {
        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'teams:ERROR',
            'log_context' => 'test',
            'log_teams_url' => 'https://outlook.webhook.office.com/test',
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testLokiWithUrlCreatesLogger(): void
    {
        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'loki:INFO',
            'log_context' => 'test',
            'log_loki_url' => 'https://loki.example.com/loki/api/v1/push',
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testWebhookWithUrlCreatesLogger(): void
    {
        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'webhook:WARNING',
            'log_context' => 'test',
            'log_webhook_url' => 'https://api.example.com/logs',
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testSlackWithoutUrlIsSkipped(): void
    {
        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'slack:ERROR,null:DEBUG',
            'log_context' => 'test',
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testUrlHandlersWithEmptyUrlsAreSkipped(): void
    {
        $handler = new LoggerHandler();
        $logger = $handler($this->env([
            'log_handlers' => 'slack:ERROR,teams:ERROR,loki:INFO,webhook:WARNING,null:DEBUG',
            'log_context' => 'test',
            'log_slack_url' => '',
            'log_teams_url' => '',
            'log_loki_url' => '',
            'log_webhook_url' => '',
        ]));

        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     * @return \Closure(string): mixed
     */
    private function env(array $data): \Closure
    {
        return static fn (string $key): mixed => $data[strtolower($key)] ?? null;
    }

    private function buildRedis(): Redis
    {
        $redis = new Redis();
        $redis->connect($_ENV['redis_host'] ?? 'redis_test', (int) ($_ENV['redis_port'] ?? 6379));

        return $redis;
    }
}
