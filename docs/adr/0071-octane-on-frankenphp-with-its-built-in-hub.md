# ADR 0071: The application runs on Octane with FrankenPHP, whose built-in Mercure hub serves the stream, in development as in production

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** none; building RFC 0013's setup
- **Supersedes:** ADR 0069

## Context

ADR 0069 ran development in Sail with `artisan serve` and a separate Mercure hub container, while production ran FrankenPHP with its built-in hub. RFC 0013 kept Laravel Octane as something to add "when profiling says so". Building the setup showed what that difference costs: development ran neither the server nor the hub that production runs, and Octane's worker mode, which keeps the application in memory between requests, would only meet the code after it was deployed. FrankenPHP is a Caddy server with Mercure compiled in, and Octane runs it directly.

## Decision

The application runs on Laravel Octane with FrankenPHP in development, CI and production. In development, Sail's application container starts `octane:start --server=frankenphp --watch`, and FrankenPHP's own file watcher reloads the workers. The stream's hub is FrankenPHP's built-in Mercure (1.x), configured with Mercure 1.0's trusted issuer and RFC 9068 access tokens; because Octane's own `mercure` option is a flat list that cannot express that block, the whole block is passed to Caddy through `octane.caddy.env`. Caddy's data, the hub's history included, lives under `storage/caddy`. The separate Mercure container is gone.

Everything else in ADR 0069 stands: development runs in Sail containers; PostgreSQL uses the `pgvector/pgvector` image with Sail's `testing` database, the same image CI runs as a service; Mailpit catches mail; queues use the database in v1, moving to Redis later being a configuration change.

## Consequences

- Development, CI and production run the same server and the same hub.
- Worker mode is the default from the first line of code, so state must never live in static properties. An architecture test fails on any static property in `src/`, and per-request state is bound as `scoped`, which Octane resets on every request.
- The hub shares the application's origin, so the stream needs no separate host or port.
- The FrankenPHP binary is downloaded by `octane:install` and not committed.

## Alternatives

- **ADR 0069's split: `artisan serve` and a Mercure container in development, FrankenPHP in production.** Simpler to start, and two runtimes to keep in step.
- **Octane later, when profiling asks for it.** RFC 0013's position, and worker-mode bugs found in production rather than on a laptop.
- **Octane's `mercure` option.** Less configuration, and it cannot express Mercure 1.0's issuer block.
