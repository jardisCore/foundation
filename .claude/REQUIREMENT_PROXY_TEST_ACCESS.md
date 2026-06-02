# Requirement: LoadClassFromProxy fuer Tests zugaenglich machen

## Context

`LoadClassFromProxy` ist das primaere Werkzeug fuer Mock-Injection in Integration-Tests. Ueber `addProxy()` kann jede Klasse, die tief im System per `handle()` aufgerufen wird, durch einen Mock ersetzt werden. Das Problem: Die `LoadClassFromProxy`-Instanz wird in `Domain::getClassVersion()` erzeugt und sofort an ClassVersion weitergereicht — danach ist sie unerreichbar. Weder ClassVersion, noch DomainKernel, noch Factory bieten einen Zugriffspfad.

**Ziel:** Tests sollen elegant Proxies registrieren koennen, ohne Production-Interfaces zu verschmutzen.

---

## Empfohlener Ansatz: Getter auf konkreten Klassen + Test-Helper

### Warum dieser Ansatz?

- **Minimale Production-Aenderung:** Nur 2 Getter auf konkreten Klassen (ClassVersion, DomainKernel)
- **PSR-Interfaces bleiben rein:** Kein `addProxy()` auf DomainKernelInterface — keine Test-Concerns in Contracts
- **Konsistent mit bestehendem Pattern:** `TestKernelFactory::setEnv()` nutzt bereits Zugriff auf Kernel-Internals
- **Getter sind architektonisch legitim:** `getClassVersion()` ist analog zu `getFactory()` — exponiert einen verwalteten Dienst

---

## Aenderungen

### 1. ClassVersion — Getter fuer ProxyFinder

**Datei:** `jardissupport/classversion` — `src/ClassVersion.php`

```php
public function getProxyClassFinder(): ClassVersionInterface
{
    return $this->proxyClassFinder;
}
```

Exponiert die ProxyFinder-Instanz. Nuetzlich fuer Tests und Debugging (analog: TracingClassVersion wrapped ebenfalls ClassVersion).

### 2. DomainKernel — Getter fuer ClassVersion

**Datei:** `jardiscore/foundation` — `src/Adapter/DomainKernel.php`

```php
public function getClassVersion(): ?ClassVersionInterface
{
    return $this->classVersion;
}
```

Nur auf der **konkreten Klasse**, NICHT auf DomainKernelInterface. ClassVersion ist ein Support-Layer-Detail, kein Domain-Vertrag.

### 3. TestKernelFactory — addProxy Helper

**Datei:** `jardiscore/foundation` — `tests/Integration/Adapter/TestKernelFactory.php`

```php
use JardisSupport\ClassVersion\ClassVersion;
use JardisSupport\ClassVersion\Reader\LoadClassFromProxy;

public static function addProxy(
    DomainKernelInterface $kernel,
    string $className,
    object $proxy,
    ?string $version = null
): void {
    if (!$kernel instanceof DomainKernel) {
        throw new \RuntimeException('Proxy injection requires DomainKernel');
    }

    $classVersion = $kernel->getClassVersion();
    if (!$classVersion instanceof ClassVersion) {
        throw new \RuntimeException('Proxy injection requires ClassVersion');
    }

    $proxyFinder = $classVersion->getProxyClassFinder();
    if (!$proxyFinder instanceof LoadClassFromProxy) {
        throw new \RuntimeException('Proxy injection requires LoadClassFromProxy');
    }

    $proxyFinder->addProxy($className, $proxy, $version);
}
```

### 4. TestKernelFactory::create() — ClassVersion mitgeben

Aktuell erzeugt `create()` einen DomainKernel **ohne** ClassVersion:
```php
return new DomainKernel($appRoot, $domainRoot); // kein classVersion-Parameter!
```

Damit `addProxy()` funktioniert, muss `create()` eine Default-ClassVersion mitliefern:

```php
public static function create(): DomainKernelInterface
{
    $appRoot = dirname(__DIR__, 2);
    $domainRoot = $appRoot;
    $config = new ClassVersionConfig();

    $classVersion = new ClassVersion(
        $config,
        new LoadClassFromSubDirectory($config),
        new LoadClassFromProxy($config),
    );

    return new DomainKernel($appRoot, $domainRoot, $classVersion);
}
```

Da `Domain::getClassVersion()` jetzt immer ClassVersion liefert, sollte der Test-Kernel dasselbe tun. Das spiegelt das reale Verhalten wider.

---

## Test-Nutzung

```php
// Setup
$kernel = TestKernelFactory::create();

// Mock registrieren
$mockRepository = new MockOrderRepository();
TestKernelFactory::addProxy($kernel, OrderRepository::class, $mockRepository);

// System-Code ausfuehren — handle(OrderRepository::class) liefert den Mock
$context = new BoundedContext($kernel, $request);
$result = $context->handle(SomeService::class);

// Cleanup (optional)
TestKernelFactory::removeProxy($kernel, OrderRepository::class);
```

---

## Betroffene Dateien

| Datei | Projekt | Aenderung |
|-------|---------|-----------|
| `src/ClassVersion.php` | classversion | `getProxyClassFinder()` Getter |
| `tests/Unit/ClassVersionTest.php` | classversion | Test fuer neuen Getter |
| `src/Adapter/DomainKernel.php` | foundation | `getClassVersion()` Getter |
| `tests/Unit/Adapter/DomainKernelTest.php` | foundation | Test fuer neuen Getter |
| `tests/Integration/Adapter/TestKernelFactory.php` | foundation | `addProxy()`, `removeProxy()`, `create()` mit ClassVersion |

## Nicht betroffen

- **PSR-Interfaces:** DomainKernelInterface, ClassVersionInterface, FactoryInterface — alle unveraendert
- **Factory.php:** Keine Aenderung
- **Domain.php:** Keine Aenderung (hat LoadClassFromProxy bereits in getClassVersion())
- **Builder-Projekt:** Keine Aenderung

---

## Verworfene Alternativen

| Ansatz | Warum verworfen |
|--------|-----------------|
| `addProxy()` auf DomainKernelInterface | Verschmutzt PSR-Interface mit Test-Concern (SRP-Verletzung) |
| `addProxy()` direkt auf DomainKernel | DomainKernel ist Service-Locator, nicht Proxy-Manager |
| Reine Reflection (wie setEnv) | Fragil — bricht bei Property-Umbenennung. Fuer setEnv akzeptabel (1 Property), fuer 3-stufige Chain zu riskant |
| TestableClassVersion Decorator | Mehr Boilerplate, jede Test-Domain muss getClassVersion() overriden |

---

## Verifikation

1. ClassVersion-Tests: `cd jardissupport/classversion && docker compose run --rm phpcli vendor/bin/phpunit`
2. Foundation-Tests: `cd jardiscore/foundation && docker compose run --rm phpcli vendor/bin/phpunit`
3. Manueller Test: Kernel via TestKernelFactory erstellen, Proxy registrieren, via handle() abrufen — Mock kommt zurueck
