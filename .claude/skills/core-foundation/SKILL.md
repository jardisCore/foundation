---
name: core-foundation
description: JardisApp ENV-driven integration platform — DB, Cache, Logger, Events, HTTP, Mail, Filesystem.
user-invocable: true
---

# FOUNDATION_SKILL
> `jardiscore/foundation` | NS: `JardisCore\Foundation` | Extends `Kernel\DomainApp` with ENV-assembled services

## ARCHITECTURE
```
jardissupport/contract  →  Interfaces (PSR + Jardis)
jardiscore/kernel       →  DomainApp, DomainKernel, BoundedContext, Response Pipeline
jardiscore/foundation   →  JardisApp + 8 ENV-driven Handlers
```
`class MyApp extends JardisApp` — all hooks assembled from `.env`, adapters optional via `class_exists()`.

## CORE CLASS

`JardisApp extends DomainApp` — overrides 8 hooks (lazy, `??=`). Redis + PDO built once, shared between Cache and Logger.

| Hook | Handler | Return type |
|------|---------|-------------|
| `dbConnection()` | `ConnectionHandler` | `ConnectionPoolInterface\|PDO\|false\|null` |
| `redis()` | `RedisHandler` | `?Redis` |
| `cache()` | `CacheHandler` | `CacheInterface\|false\|null` |
| `logger()` | `LoggerHandler` | `LoggerInterface\|false\|null` |
| `eventDispatcher()` | `EventDispatcherHandler` | `EventDispatcherInterface\|false\|null` |
| `httpClient()` | `HttpClientHandler` | `ClientInterface\|false\|null` |
| `mailer()` | `MailerHandler` | `MailerInterface\|false\|null` |
| `filesystem()` | `FilesystemHandler` | `FilesystemServiceInterface\|false\|null` |

**Three-State Resolution** (from `Kernel\DomainApp`):
| Return | Meaning |
|--------|---------|
| object | Use locally + share in SharedRegistry (first-write-wins) |
| null | No local service, use shared fallback |
| false | Explicitly disabled, no fallback |

**Hook override:** extend `JardisApp`, override specific hook(s); remainder still come from ENV.

## HANDLERS

All: stateless `__invoke`, receive `Closure $env` for ENV access. Null if adapter package missing.

### ConnectionHandler `(Closure $env): ConnectionPoolInterface|PDO|null`
- ENV: `DB_DRIVER` (mysql|pgsql|sqlite), `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_DATABASE`, `DB_CHARSET`
- Read replicas: `DB_READER1_HOST`, `DB_READER1_PORT`, `DB_READER2_HOST`, … (missing values inherit from writer)
- Readers + `jardisadapter/dbconnection` installed → `ConnectionPool`; otherwise → plain `PDO`
- PostgreSQL DSN: `options='--client_encoding=$charset'`

### RedisHandler `(Closure $env, string $prefix): ?Redis`
- ENV: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DATABASE`
- null if `REDIS_HOST` not set. One shared connection passed to Cache + Logger.

### CacheHandler `(Closure $env, ?PDO, ?Redis): ?CacheInterface`
- `CACHE_LAYERS` comma-separated, left = highest priority: `memory`→`CacheMemory`, `apcu`→`CacheApcu`, `redis`→`CacheRedis`, `db`→`CacheDatabase`
- `CACHE_NAMESPACE` for key prefix; `CACHE_DB_TABLE` for db layer (default: `cache`)
- null if `jardisadapter/cache` missing

### LoggerHandler `(Closure $env, ?Redis): ?LoggerInterface`
- `LOG_HANDLERS` comma-separated `handler:LEVEL` pairs (level optional, falls back to `LOG_LEVEL`, default: INFO)
- Handlers: `file`, `console`, `errorlog`, `syslog`, `browserconsole`, `redis`, `slack`, `teams`, `loki`, `webhook`, `null`
- Manual-only (via hook override): `database`, `email`, `stash`, `redismq`, `kafkamq`, `rabbitmq`
- `LOG_CONTEXT` (logger name), `LOG_FILE_PATH`, `LOG_SLACK_URL`, `LOG_TEAMS_URL`, `LOG_LOKI_URL`, `LOG_WEBHOOK_URL`
- null if `jardisadapter/logger` missing

### EventDispatcherHandler `(callable ...$configurators): ?EventDispatcherInterface`
- No ENV — pure in-memory. Configurators receive `EventListenerRegistryInterface`, register listeners.
- Without configurators: empty provider (listeners registered manually later).
- null if `jardisadapter/eventdispatcher` missing

### HttpClientHandler `(Closure $env): ?ClientInterface`
- ENV: `HTTP_BASE_URL`, `HTTP_TIMEOUT`, `HTTP_CONNECT_TIMEOUT`, `HTTP_VERIFY_SSL`
- Auth: `HTTP_BEARER_TOKEN` or `HTTP_BASIC_USER`/`HTTP_BASIC_PASSWORD`
- Retry: `HTTP_MAX_RETRIES`, `HTTP_RETRY_DELAY_MS` (exponential backoff)
- null if `jardisadapter/http` missing

### MailerHandler `(Closure $env): ?MailerInterface`
- ENV: `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION` (tls|ssl|none), `MAIL_USERNAME`, `MAIL_PASSWORD`
- Optional: `MAIL_TIMEOUT`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- null if `jardisadapter/mailer` missing or `MAIL_HOST` not set

### FilesystemHandler `(): ?FilesystemServiceInterface`
- No ENV — stateless factory. Returns `new FilesystemService()`.
- Project usage: `$kernel->filesystem()->local('/path')` or `->s3('bucket', 'region', $key, $secret)`
- Extended config: `FilesystemService::create(LocalConfig|S3Config)`
- null if `jardisadapter/filesystem` missing

## ENUMS

| Enum | Values |
|------|--------|
| `CacheLayer` (`src/Data/CacheLayer.php`) | `memory`, `apcu`, `redis`, `db` |
| `LogHandler` (`src/Data/LogHandler.php`) | `file`, `console`, `errorlog`, `syslog`, `browserconsole`, `redis`, `slack`, `teams`, `loki`, `webhook`, `null` |

## ENV VARIABLES

### Database
```env
DB_DRIVER=mysql          # mysql|pgsql|sqlite
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=secret
DB_DATABASE=myapp
DB_CHARSET=utf8mb4
DB_READER1_HOST=replica1
DB_READER1_PORT=3306     # optional, inherits writer value
DB_READER2_HOST=replica2
```

### Redis
```env
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DATABASE=0
```

### Cache
```env
CACHE_LAYERS=memory,redis     # order = lookup priority, left first
CACHE_NAMESPACE=myapp
CACHE_DB_TABLE=cache          # only for db layer
```

### Logger
```env
LOG_HANDLERS=file:ERROR,console:DEBUG
LOG_LEVEL=INFO                # fallback level when omitted from handler
LOG_CONTEXT=myapp
LOG_FILE_PATH=/var/log/app.log
LOG_SLACK_URL=
LOG_TEAMS_URL=
LOG_LOKI_URL=
LOG_WEBHOOK_URL=
```

### HTTP Client
```env
HTTP_BASE_URL=https://api.example.com
HTTP_TIMEOUT=30
HTTP_CONNECT_TIMEOUT=10
HTTP_VERIFY_SSL=true
HTTP_BEARER_TOKEN=
HTTP_BASIC_USER=
HTTP_BASIC_PASSWORD=
HTTP_MAX_RETRIES=3
HTTP_RETRY_DELAY_MS=100
```

### Mailer
```env
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls           # tls|ssl|none
MAIL_USERNAME=user@example.com
MAIL_PASSWORD=secret
MAIL_TIMEOUT=30
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=My Application
```

## RULES
1. `class MyApp extends JardisApp` — `kernel()` bootstraps lazily, no constructor arg needed.
2. Services via Kernel: `$this->resource()->cache()`, `->logger()`, `->dbConnection()`.
3. ENV loaded by `DomainApp::loadEnv()` from `domainRoot/.env` — single stage, no cascade.
4. ENV access in hooks: `$this->env('key')` (case-insensitive, private ENV > `$_ENV`).
5. Adapters are optional — each Handler checks `class_exists()` at runtime.
6. No `get` prefix on Kernel methods: `kernel()`, `result()`, `resource()`, `payload()`, `version()`.

## DEPENDENCIES
**Required:** `jardiscore/kernel`, `ext-pdo`
**Optional:** `jardisadapter/cache`, `jardisadapter/dbconnection`, `jardisadapter/logger`, `jardisadapter/eventdispatcher`, `jardisadapter/http`, `jardisadapter/mailer`, `jardisadapter/filesystem`, `ext-redis`

## LAYER
Foundation is the outermost integration layer. Domain code (`jardiscore/kernel`, `jardissupport/*`) never imports Foundation classes.
