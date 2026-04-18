# Jardis Foundation

![Build Status](https://github.com/jardisCore/foundation/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![PSR-3](https://img.shields.io/badge/PSR--3-Logger-blue.svg)](https://www.php-fig.org/psr/psr-3/)
[![PSR-14](https://img.shields.io/badge/PSR--14-Event%20Dispatcher-blue.svg)](https://www.php-fig.org/psr/psr-14/)
[![PSR-16](https://img.shields.io/badge/PSR--16-Simple%20Cache-blue.svg)](https://www.php-fig.org/psr/psr-16/)
[![PSR-18](https://img.shields.io/badge/PSR--18-HTTP%20Client-blue.svg)](https://www.php-fig.org/psr/psr-18/)

> Part of the **[Jardis Business Platform](https://jardis.io)** — Enterprise-grade PHP components for Domain-Driven Design

---

## The Integration Platform for Jardis DDD Projects

**Jardis Foundation** is the runtime heart of every Jardis DDD project. It connects the full power of the Jardis ecosystem — database connections, caching, logging, event dispatching, HTTP communication — into a single, ENV-driven entry point.

When you generate a DDD project with the **Jardis Builder**, the resulting code extends `JardisApp`. Foundation takes care of assembling all infrastructure services so your domain code stays clean, portable and focused on business logic.

### What Foundation delivers

- **Pure Hexagonal Architecture** — your domain core has zero knowledge of infrastructure. Foundation wires adapters behind PSR interfaces, respecting the Dependency Inversion Principle at every layer.
- **Seamless Builder Integration** — the Jardis Builder generates BoundedContexts, Aggregates, Repositories and Commands that run on Foundation out of the box. No manual wiring, no bootstrap ceremony.
- **Jardis Ecosystem in one place** — Foundation brings together 8+ specialized Jardis packages (Kernel, Cache, DbConnection, Logger, EventDispatcher, HTTP, Mailer, Filesystem) through a unified ENV configuration. Install what you need, ignore what you don't.
- **Convention over Configuration** — a single `.env` file replaces hundreds of lines of container configuration. Every service auto-assembles from environment variables.
- **PSR-compliant throughout** — PSR-3 (Logger), PSR-14 (Event Dispatcher), PSR-16 (Cache), PSR-18 (HTTP Client). Your domain code depends on standards, never on implementations.

### From Builder to running application

```
Jardis Builder                     Jardis Foundation
─────────────                      ─────────────────
Generates:                         Provides at runtime:
  BoundedContexts                    Database (PDO / ConnectionPool)
  Aggregates + Entities              Redis (shared connection)
  Repositories + Pipeline            Cache (multi-layer: memory, apcu, redis, db)
  Commands + Queries                 Logger (multi-handler: file, slack, loki, ...)
  Domain Events                      Event Dispatcher (PSR-14)
         │                           HTTP Client (PSR-18)
         │                           Mailer (SMTP)
         │                           Filesystem (Local + S3)
         │                                    │
         └──── extends JardisApp ─────────────┘
```

---

## DomainApp vs. JardisApp

| | `DomainApp` (Kernel) | `JardisApp` (Foundation) |
|---|---|---|
| **Package** | `jardiscore/kernel` | `jardiscore/foundation` |
| **Dependencies** | Only Kernel + PSR interfaces | Kernel + optional adapters |
| **Services** | Override hooks manually | Hooks filled from ENV |
| **Use when** | Full control, own infrastructure | Jardis ecosystem, Builder projects |

```php
// Full control — wire everything yourself:
class Ecommerce extends DomainApp { }

// Jardis ecosystem — services from .env:
class Ecommerce extends JardisApp { }
```

---

## Installation

```bash
composer require jardiscore/foundation
```

Optional adapters — install only what you need:

```bash
composer require jardisadapter/cache             # Multi-layer caching (Memory, APCu, Redis, Database)
composer require jardisadapter/dbconnection      # ConnectionPool with read/write splitting
composer require jardisadapter/logger            # Log handlers (File, Slack, Teams, Loki, etc.)
composer require jardisadapter/eventdispatcher   # PSR-14 Event Dispatching
composer require jardisadapter/http              # PSR-18 HTTP Client with handler pipeline
composer require jardisadapter/mailer            # SMTP Mailer with STARTTLS, AUTH, HTML/Text
composer require jardisadapter/filesystem        # Local + S3 filesystem abstraction
```

---

## Quick Start

### 1. Create a `.env` in your domain root

```env
DB_DRIVER=mysql
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=secret
DB_DATABASE=shop

REDIS_HOST=localhost

CACHE_LAYERS=memory,redis
LOG_HANDLERS=file:INFO,console:DEBUG
LOG_CONTEXT=shop
LOG_FILE_PATH=/var/log/shop.log

HTTP_BASE_URL=https://api.payment.example.com
HTTP_BEARER_TOKEN=pk_live_...
```

### 2. Extend JardisApp

```php
use JardisCore\Foundation\JardisApp;

class Ecommerce extends JardisApp
{
    public function order(): PlaceOrder
    {
        return new PlaceOrder($this->kernel());
    }
}
```

### 3. Use it

```php
$shop = new Ecommerce();
$response = $shop->order()(['customer' => 'Acme', 'total' => 99.90]);

$response->isSuccess();   // true
$response->getData();     // ['PlaceOrder' => ['orderId' => 42]]
```

That's it. No bootstrap file. No container setup. Services assembled from `.env`.

---

## ENV Configuration

### Database

```env
DB_DRIVER=mysql          # mysql|pgsql|sqlite
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=secret
DB_DATABASE=myapp
DB_CHARSET=utf8mb4

# Optional: Read Replicas (auto-builds ConnectionPool when jardisadapter/dbconnection is installed)
DB_READER1_HOST=replica1
DB_READER2_HOST=replica2
# Missing values inherit from writer
```

### Redis (shared between cache and logger)

```env
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DATABASE=0
```

### Cache

```env
# Available layers: memory, apcu, redis, db (order = lookup priority, left first)
CACHE_LAYERS=memory,redis,db
CACHE_NAMESPACE=myapp
CACHE_DB_TABLE=cache         # only for db layer
```

### Logger

```env
# Handlers: file, console, errorlog, syslog, browserconsole, redis, slack, teams, loki, webhook, null
# Manual (via hook override): database, email, stash, redismq, kafkamq, rabbitmq
# Levels: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
# Syntax: handler:LEVEL,handler:LEVEL
LOG_HANDLERS=file:ERROR,console:DEBUG
LOG_CONTEXT=myapp
LOG_FILE_PATH=/var/log/app.log
#LOG_SLACK_URL=
#LOG_TEAMS_URL=
#LOG_LOKI_URL=
#LOG_WEBHOOK_URL=
```

### Event Dispatcher

No ENV configuration needed — the PSR-14 event dispatcher is a pure in-memory service. Install `jardisadapter/eventdispatcher` and it's available automatically.

### HTTP Client

```env
HTTP_BASE_URL=https://api.example.com
HTTP_TIMEOUT=30
HTTP_CONNECT_TIMEOUT=10
HTTP_VERIFY_SSL=true

# Authentication (one of):
HTTP_BEARER_TOKEN=
HTTP_BASIC_USER=
HTTP_BASIC_PASSWORD=

# Retry (optional):
HTTP_MAX_RETRIES=3
HTTP_RETRY_DELAY_MS=100
```

---

## Hook Overrides

JardisApp fills hooks from ENV — but you can override any of them:

```php
class Ecommerce extends JardisApp
{
    // Own cache instead of ENV-based:
    protected function cache(): CacheInterface|false|null
    {
        return new MySpecialCache();
    }

    // Rest (connection, redis, logger, eventDispatcher, httpClient, mailer, filesystem) still comes from ENV
}
```

Three-state resolution (inherited from DomainApp):

| Return | Meaning |
|--------|---------|
| **object** | Use this service. Share it with other DomainApps (first-write-wins). |
| **null** | No local service. Use shared from another DomainApp if available. |
| **false** | Explicitly disabled. Don't use shared fallback. |

---

## Architecture

```
DomainApp (jardiscore/kernel)
    |
JardisApp (jardiscore/foundation) extends DomainApp
    |-- connection()       -> ConnectionHandler       -> PDO or ConnectionPool from ENV
    |-- redis()            -> RedisHandler            -> shared Redis from ENV
    |-- cache()            -> CacheHandler            -> multi-layer Cache (PSR-16)
    |-- logger()           -> LoggerHandler           -> multi-handler Logger (PSR-3)
    |-- eventDispatcher()  -> EventDispatcherHandler  -> Event Dispatcher (PSR-14)
    |-- httpClient()       -> HttpClientHandler       -> HTTP Client (PSR-18)
    |-- mailer()           -> MailerHandler           -> SMTP Mailer
    |-- filesystem()       -> FilesystemHandler       -> Filesystem Factory
```

### Directory Structure

```
src/
├── JardisApp.php                      # Extends DomainApp, overrides service hooks
├── Data/
│   ├── CacheLayer.php                 # Enum: memory, apcu, redis, db
│   └── LogHandler.php                 # Enum: file, console, errorlog, syslog, ...
└── Handler/
    ├── ConnectionHandler.php          # DB_* -> PDO or ConnectionPool
    ├── RedisHandler.php               # REDIS_* -> shared Redis connection
    ├── CacheHandler.php               # CACHE_LAYERS -> multi-layer Cache
    ├── LoggerHandler.php              # LOG_HANDLERS -> multi-handler Logger
    ├── EventDispatcherHandler.php     # -> PSR-14 Event Dispatcher
    ├── HttpClientHandler.php          # HTTP_* -> PSR-18 HTTP Client
    ├── MailerHandler.php              # MAIL_* -> SMTP Mailer
    └── FilesystemHandler.php          # -> Filesystem Factory
```

11 source files. Adapters are optional — Foundation checks `class_exists()` at runtime.

### Package Layering

```
jardissupport/contract       Interfaces (PSR + Jardis contracts)
        │
jardiscore/kernel            DDD Core (DomainApp, DomainKernel, BoundedContext, Response Pipeline)
        │
jardiscore/foundation        Integration Platform (JardisApp + ENV-driven Handlers)
        │
    ┌───┼───┬───────────┬───────────────┬──────────┬──────────┬────────────┐
    │       │           │               │          │          │            │
  cache  dbconnection  logger   eventdispatcher  http     mailer    filesystem
 (PSR-16)            (PSR-3)      (PSR-14)     (PSR-18)
```

---

## The Jardis Ecosystem

Foundation brings together these packages at runtime:

| Package | Role | PSR |
|---------|------|-----|
| `jardiscore/kernel` | DDD core: DomainApp, DomainKernel, BoundedContext, Response Pipeline | — |
| `jardissupport/contract` | Shared interfaces across all packages | — |
| `jardisadapter/cache` | Multi-layer caching (Memory, APCu, Redis, Database) | PSR-16 |
| `jardisadapter/dbconnection` | ConnectionPool with read/write splitting | — |
| `jardisadapter/logger` | Log handlers (File, Console, Redis, Slack, Teams, Loki, Webhook) | PSR-3 |
| `jardisadapter/eventdispatcher` | Event dispatching with priority and type-hierarchy matching | PSR-14 |
| `jardisadapter/http` | HTTP client with handler pipeline, retry, auth | PSR-18 |
| `jardisadapter/mailer` | SMTP mailer with STARTTLS, AUTH, HTML/Text, attachments | — |
| `jardisadapter/filesystem` | Local + S3 filesystem abstraction with unified API | — |

All adapters are optional. Install what your project needs — Foundation detects availability at runtime.

---

## Documentation

Full documentation, guides, and API reference:

**[docs.jardis.io/en/core/foundation](https://docs.jardis.io/en/core/foundation)**

---

## License

Licensed under the [MIT License](LICENSE.md).

---

**[Jardis](https://jardis.io)** · [Documentation](https://docs.jardis.io) · [Headgent](https://headgent.com)

<!-- BEGIN jardis/dev-skills README block — do not edit by hand -->
## KI-gestützte Entwicklung

Dieses Package liefert einen Skill für Claude Code, Cursor, Continue und Aider mit. Installation im Konsumentenprojekt:

```bash
composer require --dev jardis/dev-skills
```

Mehr Details: <https://docs.jardis.io/skills>
<!-- END jardis/dev-skills README block -->
