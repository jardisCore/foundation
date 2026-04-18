# jardiscore/foundation

Integration platform for Jardis DDD projects: `JardisApp extends DomainApp` with eight ENV-built service hooks (`dbConnection`, `redis`, `cache`, `logger`, `eventDispatcher`, `httpClient`, `mailer`, `filesystem`). In your project: `class MyApp extends JardisApp` — maintain `.env`, done.

## Usage essentials

- **Handlers are stateless callable classes** (`__invoke(Closure $env, …)`) under `src/Handler/`. Shared `Redis` and `PDO` are built once by `JardisApp` and passed to `CacheHandler` + `LoggerHandler` — no duplicate connections.
- **Adapters are optional.** Each Handler checks `class_exists(...)` at runtime; if `jardisadapter/cache|logger|http|mailer|filesystem|eventdispatcher|dbconnection` is missing, the corresponding hook returns `null`. No bootstrap error, no hard deps.
- **Three-State Service Resolution (inherited from `Kernel\DomainApp`):** Hook returns object → used locally + shared in `ServiceRegistry` (first-write-wins); `null` → no local service, use shared fallback from registry; `false` → explicitly disabled, no fallback.
- **ENV schema convention-driven:** `DB_DRIVER/HOST/PORT/USER/PASSWORD/DATABASE/CHARSET` (+ `DB_READER1_HOST…` automatically activates `ConnectionPool` when `jardisadapter/dbconnection` is installed), `REDIS_*`, `CACHE_LAYERS=memory,redis,db`, `LOG_HANDLERS=file:ERROR,console:DEBUG`, `HTTP_*`, `MAIL_*`. Single `.env` in `domainRoot`, no cascade; keys internally lowercase.
- **Hook override for special cases:** In `MyApp` override individual methods (e.g. `cache()`) — all other services continue to come from ENV via `JardisApp`. No own constructor; `kernel()` is `final protected` and must never be overridden.
- **PSR compliance of returned services:** PSR-3 (Logger), PSR-14 (EventDispatcher), PSR-16 (Cache), PSR-18 (HTTP Client). `dbConnection()` returns `ConnectionPoolInterface|PDO|null` — plain PDO is the default, pool only when needed.

## Full reference

https://docs.jardis.io/en/core/foundation
