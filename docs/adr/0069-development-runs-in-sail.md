# ADR 0069: Development runs in Sail, on the same PostgreSQL and a Mercure hub, with database queues in v1

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0013

## Context

The application needs PostgreSQL with pgvector, a Mercure hub, a mail catcher, PHP 8.5 with the right extensions, and Node. Tests must run on the same database as production (ADR 0049). Sail is already installed with the starter kit.

## Decision

Development runs entirely in containers with Laravel Sail: the application container running `composer dev`, PostgreSQL on the `pgvector/pgvector` image at Sail's major version with Sail's `testing` database, a Mercure hub added by hand with the `dunglas/mercure` image, and Mailpit. CI runs the same PostgreSQL image as a service container. Production runs FrankenPHP with its built-in Mercure hub. Queues use the database in v1, everywhere; moving them to Redis later is a configuration change.

## Consequences

- Docker is the only thing a machine needs.
- What runs locally, in CI and in production is the same database.
- The runtime is PostgreSQL and one hub until a queue needs more.

## Alternatives

- **Services in Docker, the application on the host.** Every machine needs the right PHP and Node.
- **Redis from the start.** A second datastore before anything needs one.
