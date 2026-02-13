# Jardis Foundation

![Build Status](https://github.com/jardisCore/foundation/actions/workflows/ci.yml/badge.svg)
[![License: PolyForm Noncommercial](https://img.shields.io/badge/License-PolyForm%20Noncommercial-blue.svg)](LICENSE)
[![Commercial License](https://img.shields.io/badge/Commercial%20License-Available-green.svg)](COMMERCIAL.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PSR-3](https://img.shields.io/badge/PSR--3-Logger-blue.svg)](https://www.php-fig.org/psr/psr-3/)
[![PSR-4](https://img.shields.io/badge/PSR--4-Autoloader-blue.svg)](https://www.php-fig.org/psr/psr-4/)
[![PSR-11](https://img.shields.io/badge/PSR--11-Container-blue.svg)](https://www.php-fig.org/psr/psr-11/)
[![PSR-12](https://img.shields.io/badge/PSR--12-Code%20Style-blue.svg)](https://www.php-fig.org/psr/psr-12/)
[![PSR-16](https://img.shields.io/badge/PSR--16-Simple%20Cache-blue.svg)](https://www.php-fig.org/psr/psr-16/)

> Part of the **[Jardis Ecosystem](https://jardis.io)** — A modular DDD framework for PHP

DDD Foundation for PHP. Domain orchestration with BoundedContext, Request/Response handling and zero-config infrastructure services for Database, Cache, Logger, and Messaging.

---

## Features

- **Domain Context** — Base Domain class with automatic path detection and kernel bootstrapping
- **Bounded Context** — Request/Response handling with Factory integration and version-based class resolution
- **Multi-Layer Caching** — Memory → APCu → Redis → Database cascade
- **Connection Pool** — Connection pooling with read/write splitting via `ConnectionPoolInterface`
- **Smart Logging** — Conditional routing, sampling, fingers-crossed buffering, 20 handlers (File, Slack, Teams, Loki, Kafka, RabbitMQ, Email, Webhook, etc.)
- **Flexible Messaging** — Redis Streams, Kafka, RabbitMQ with priority-based selection
- **SharedRuntime** — Organization-wide infrastructure configuration as sibling directory, auto-detected
- **Cross-Domain Connection Sharing** — Multiple kernels automatically share connections
- **Dynamic Class Versioning** — Load different implementations by version at runtime via SubDirectory and Proxy loaders

---

## Installation

```bash
composer require jardiscore/foundation
```

## Quick Start

```php
use JardisCore\Foundation\Domain;
use JardisCore\Foundation\Context\BoundedContext;
use JardisCore\Foundation\Context\Request;
use JardisPsr\Foundation\ResponseInterface;

// Standard: ClassVersion with SubDirectory and Proxy loaders is active by default.
class MyDomain extends Domain
{
    public function myContext(Request $request): ResponseInterface
    {
        $context = new BoundedContext($this->getKernel(), $request);
        return $context->handle(MyBoundedContext::class);
    }
}

// Custom: Provide own ClassVersionConfig
class EcommerceDomain extends Domain
{
    protected function getClassVersion(
        ?ClassVersionConfigInterface $config = null,
    ): ?ClassVersionInterface {
        $config = new ClassVersionConfig(
            version: ['v1' => ['legacy'], 'v2' => ['current']],
            fallbacks: ['v2' => ['v1']]
        );

        return parent::getClassVersion($config);
    }
}
```

Infrastructure services via DomainKernel:

```php
use JardisCore\Foundation\Adapter\DomainKernel;

$kernel = new DomainKernel($appRoot, $domainRoot);

$pool      = $kernel->getConnectionPool(); // ConnectionPoolInterface
$cache     = $kernel->getCache();          // CacheInterface (PSR-16)
$logger    = $kernel->getLogger();         // LoggerInterface (PSR-3)
$messaging = $kernel->getMessage();        // MessagingServiceInterface
$factory   = $kernel->getFactory();        // FactoryInterface
```

Domain-specific kernels via overridable `createKernel()`:

```php
class EcommerceDomain extends Domain
{
    protected function createKernel(
        string $appRoot,
        string $domainRoot,
        ?ClassVersionInterface $classVersion,
        ResourceRegistryInterface $resources,
        ?string $sharedRuntimeRoot = null
    ): DomainKernelInterface {
        return new EcommerceKernel($appRoot, $domainRoot, $classVersion, $resources, $sharedRuntimeRoot);
    }
}
```

### SharedRuntime

Organization-wide infrastructure settings can be shared across domains via a `SharedRuntime/` directory placed as a sibling of the domain root:

```
project/
├── SharedRuntime/      # Shared .env for all domains
│   └── .env
├── EcommerceDomain/
│   └── .env            # Domain-specific overrides
└── UserDomain/
    └── .env
```

The environment cascade loads in this order (later values override earlier):

```
AppRoot (public) → Foundation (private) → SharedRuntime (private) → Domain (private)
```

`Domain` auto-detects `SharedRuntime/` as sibling of `domainRoot`. No configuration needed.

## Documentation

Full documentation, examples and API reference:

**→ [jardis.io/docs/core/foundation](https://jardis.io/docs/core/foundation)**

## Jardis Ecosystem

This package is part of the Jardis Ecosystem — a collection of modular, high-quality PHP packages designed for Domain-Driven Design.

| Category | Packages                                                                        |
|----------|-------------------------------------------------------------------------------------|
| **Core** | Foundation                                                                          |
| **Adapter** | Cache, Logger, Messaging, DbConnection                                              |
| **Support** | DotEnv, DbQuery, Validation, Factory, ClassVersion, Workflow, Data, Repository      |
| **Tools** | DomainBuilder, DbSchema                                                             |

**→ [Explore all packages](https://jardis.io/docs)**

## License

This package is licensed under the [PolyForm Noncommercial License 1.0.0](LICENSE).

For commercial use, see [COMMERCIAL.md](COMMERCIAL.md).

---

**[Jardis Ecosystem](https://jardis.io)** by [Headgent Development](https://headgent.com)
