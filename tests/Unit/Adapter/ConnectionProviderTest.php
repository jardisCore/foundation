<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Tests\Unit\Adapter;

use JardisCore\Foundation\Adapter\ConnectionProvider;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ConnectionProvider
 *
 * Tests connection registration, lazy factories, typed access, and cross-domain sharing.
 */
class ConnectionProviderTest extends TestCase
{
    protected function setUp(): void
    {
        ConnectionProvider::resetShared();
    }

    protected function tearDown(): void
    {
        ConnectionProvider::resetShared();
    }

    public function testAddPdoAndRetrieve(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $provider = new ConnectionProvider();
        $provider->addPdo('writer', $pdo);

        $this->assertTrue($provider->hasPdo('writer'));
        $this->assertSame($pdo, $provider->pdo('writer'));
    }

    public function testPdoReturnsNullWhenNotRegistered(): void
    {
        $provider = new ConnectionProvider();

        $this->assertFalse($provider->hasPdo('writer'));
        $this->assertNull($provider->pdo('writer'));
    }

    public function testAddFactoryCreatesConnectionLazily(): void
    {
        $callCount = 0;
        $pdo = new PDO('sqlite::memory:');

        $provider = new ConnectionProvider();
        $provider->addFactory('pdo.writer', function () use (&$callCount, $pdo): PDO {
            $callCount++;
            return $pdo;
        });

        $this->assertTrue($provider->hasPdo('writer'));
        $this->assertSame(0, $callCount);

        $result = $provider->pdo('writer');
        $this->assertSame($pdo, $result);
        $this->assertSame(1, $callCount);

        // Second call uses cached result
        $result2 = $provider->pdo('writer');
        $this->assertSame($pdo, $result2);
        $this->assertSame(1, $callCount);
    }

    public function testExternalConnectionTakesPriorityOverFactory(): void
    {
        $externalPdo = new PDO('sqlite::memory:');
        $factoryPdo = new PDO('sqlite::memory:');

        $provider = new ConnectionProvider();
        $provider->addPdo('writer', $externalPdo);
        $provider->addFactory('pdo.writer', fn(): PDO => $factoryPdo);

        $this->assertSame($externalPdo, $provider->pdo('writer'));
    }

    public function testFactoryDoesNotOverrideExistingFactory(): void
    {
        $pdo1 = new PDO('sqlite::memory:');
        $pdo2 = new PDO('sqlite::memory:');

        $provider = new ConnectionProvider();
        $provider->addFactory('pdo.writer', fn(): PDO => $pdo1);
        $provider->addFactory('pdo.writer', fn(): PDO => $pdo2);

        $this->assertSame($pdo1, $provider->pdo('writer'));
    }

    public function testShareAllAndMergeFromShared(): void
    {
        $pdo = new PDO('sqlite::memory:');

        // Domain A shares
        $providerA = new ConnectionProvider();
        $providerA->addPdo('writer', $pdo);
        $providerA->shareAll();

        // Domain B merges
        $providerB = new ConnectionProvider();
        $providerB->mergeFromShared();

        $this->assertTrue($providerB->hasPdo('writer'));
        $this->assertSame($pdo, $providerB->pdo('writer'));
    }

    public function testShareAllFirstWriteWins(): void
    {
        $pdo1 = new PDO('sqlite::memory:');
        $pdo2 = new PDO('sqlite::memory:');

        $providerA = new ConnectionProvider();
        $providerA->addPdo('writer', $pdo1);
        $providerA->shareAll();

        $providerB = new ConnectionProvider();
        $providerB->addPdo('writer', $pdo2);
        $providerB->shareAll();

        // New provider C merges - should get pdo1 (first-write-wins)
        $providerC = new ConnectionProvider();
        $providerC->mergeFromShared();

        $this->assertSame($pdo1, $providerC->pdo('writer'));
    }

    public function testExistingConnectionTakesPriorityOverShared(): void
    {
        $sharedPdo = new PDO('sqlite::memory:');
        $localPdo = new PDO('sqlite::memory:');

        $providerA = new ConnectionProvider();
        $providerA->addPdo('writer', $sharedPdo);
        $providerA->shareAll();

        $providerB = new ConnectionProvider();
        $providerB->addPdo('writer', $localPdo);
        $providerB->mergeFromShared();

        $this->assertSame($localPdo, $providerB->pdo('writer'));
    }

    public function testResetSharedClearsAllSharedConnections(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $providerA = new ConnectionProvider();
        $providerA->addPdo('writer', $pdo);
        $providerA->shareAll();

        ConnectionProvider::resetShared();

        $providerB = new ConnectionProvider();
        $providerB->mergeFromShared();

        $this->assertFalse($providerB->hasPdo('writer'));
    }

    public function testHasReturnsTrueForFactories(): void
    {
        $provider = new ConnectionProvider();
        $provider->addFactory('pdo.cache', fn(): PDO => new PDO('sqlite::memory:'));

        $this->assertTrue($provider->has('pdo.cache'));
        $this->assertTrue($provider->hasPdo('cache'));
    }

    public function testMultipleReaderPdoConnections(): void
    {
        $writer = new PDO('sqlite::memory:');
        $reader1 = new PDO('sqlite::memory:');
        $reader2 = new PDO('sqlite::memory:');

        $provider = new ConnectionProvider();
        $provider->addPdo('writer', $writer);
        $provider->addPdo('reader1', $reader1);
        $provider->addPdo('reader2', $reader2);

        $this->assertSame($writer, $provider->pdo('writer'));
        $this->assertSame($reader1, $provider->pdo('reader1'));
        $this->assertSame($reader2, $provider->pdo('reader2'));
    }

    public function testFactoryReturningNullIsNotCached(): void
    {
        $callCount = 0;

        $provider = new ConnectionProvider();
        $provider->addFactory('pdo.writer', function () use (&$callCount) {
            $callCount++;
            return null;
        });

        $this->assertNull($provider->pdo('writer'));
        $this->assertSame(1, $callCount);

        // Factory was consumed even though it returned null
        $this->assertNull($provider->pdo('writer'));
        $this->assertSame(1, $callCount);
    }
}
