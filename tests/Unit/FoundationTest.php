<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit;

use JardisCore\Foundation\Tests\Unit\Fixtures\EmptyDomain\TestDomain as EmptyDomain;
use JardisCore\Foundation\Tests\Unit\Fixtures\SqliteDomain\TestDomain as SqliteDomain;
use JardisCore\Foundation\Tests\Unit\Fixtures\FullDomain\TestDomain as FullDomain;
use JardisCore\Kernel\DomainApp;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use ReflectionProperty;

/**
 * Unit tests for Foundation — no external services required.
 */
class FoundationTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetSharedRegistry();
    }

    // ── No Configuration ────────────────────────────────────────────

    public function testEmptyDomainBootstrapsWithoutServices(): void
    {
        $domain = new EmptyDomain();
        $kernel = $domain->exposedKernel();

        self::assertNull($kernel->dbConnection());
        self::assertInstanceOf(CacheInterface::class, $kernel->cache());
        self::assertNull($kernel->logger());
    }

    // ── SQLite Connection ───────────────────────────────────────────

    public function testSqliteDomainCreatesInMemoryPdo(): void
    {
        $domain = new SqliteDomain();
        $kernel = $domain->exposedKernel();

        $connection = $kernel->dbConnection();
        self::assertInstanceOf(PDO::class, $connection);
        self::assertSame('sqlite', $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function testSqlitePdoHasCorrectAttributes(): void
    {
        $domain = new SqliteDomain();
        $connection = $domain->exposedKernel()->dbConnection();

        self::assertInstanceOf(PDO::class, $connection);
        self::assertSame(PDO::ERRMODE_EXCEPTION, $connection->getAttribute(PDO::ATTR_ERRMODE));
        self::assertSame(PDO::FETCH_ASSOC, $connection->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    // ── Cache ───────────────────────────────────────────────────────

    public function testMemoryCacheFromEnv(): void
    {
        $domain = new FullDomain();
        $kernel = $domain->exposedKernel();

        self::assertInstanceOf(CacheInterface::class, $kernel->cache());
    }

    public function testCacheIsUsable(): void
    {
        $domain = new FullDomain();
        $cache = $domain->exposedKernel()->cache();

        self::assertInstanceOf(CacheInterface::class, $cache);
        $cache->set('test_key', 'test_value');
        self::assertSame('test_value', $cache->get('test_key'));
    }

    // ── Logger ──────────────────────────────────────────────────────

    public function testNullLoggerFromEnv(): void
    {
        $domain = new FullDomain();
        $kernel = $domain->exposedKernel();

        self::assertInstanceOf(LoggerInterface::class, $kernel->logger());
    }

    // ── EventDispatcher ─────────────────────────────────────────────

    public function testEventDispatcherFromFullDomain(): void
    {
        $domain = new FullDomain();
        $kernel = $domain->exposedKernel();

        self::assertInstanceOf(EventDispatcherInterface::class, $kernel->eventDispatcher());
    }

    // ── HttpClient ─────────────────────────────────────────────────

    public function testHttpClientFromFullDomain(): void
    {
        $domain = new FullDomain();
        $kernel = $domain->exposedKernel();

        self::assertInstanceOf(ClientInterface::class, $kernel->httpClient());
    }

    // ── No Adapter ──────────────────────────────────────────────────

    public function testCacheAlwaysAvailable(): void
    {
        $domain = new EmptyDomain();
        $kernel = $domain->exposedKernel();

        self::assertInstanceOf(CacheInterface::class, $kernel->cache());
    }

    public function testNoLoggerWithoutDriver(): void
    {
        $domain = new EmptyDomain();
        $kernel = $domain->exposedKernel();

        self::assertNull($kernel->logger());
    }

    // ── ENV Access ──────────────────────────────────────────────────

    public function testEnvValuesAvailableViaKernel(): void
    {
        $domain = new FullDomain();
        $kernel = $domain->exposedKernel();

        self::assertSame('sqlite', $kernel->env('db_driver'));
        self::assertSame('null', $kernel->env('log_handlers'));
    }

    public function testEnvIsCaseInsensitive(): void
    {
        $domain = new FullDomain();
        $kernel = $domain->exposedKernel();

        self::assertSame($kernel->env('db_driver'), $kernel->env('DB_DRIVER'));
    }

    // ── Hook Override ───────────────────────────────────────────────

    public function testHookOverrideTakesPrecedence(): void
    {
        $expectedPdo = new PDO('sqlite::memory:');

        $domain = new class($expectedPdo) extends \JardisCore\Foundation\JardisApp {
            public function __construct(private readonly PDO $customPdo) {}

            protected function dbConnection(): \JardisSupport\Contract\DbConnection\ConnectionPoolInterface|PDO|false|null
            {
                return $this->customPdo;
            }
        };

        $kernel = (fn () => $this->kernel())->call($domain);
        self::assertSame($expectedPdo, $kernel->dbConnection());
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function resetSharedRegistry(): void
    {
        $property = new ReflectionProperty(DomainApp::class, 'sharedRegistry');
        $property->setValue(null, null);
    }
}
