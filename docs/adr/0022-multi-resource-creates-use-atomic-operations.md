# ADR 0022: Creates that need several resources use JSON:API Atomic Operations, in documented compositions only

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0004

## Context

Some things only make sense together: a thread is never empty, a request post comes with its request, a decision thread resolves with its decision. JSON:API creates one resource per request, and ADR 0004 rules out inventing a document format of our own.

## Decision

Longhand supports the [JSON:API Atomic Operations extension](https://jsonapi.org/ext/atomic/) at `POST /v1/operations`, negotiated with `ext="https://jsonapi.org/ext/atomic"`. Operations apply in order, all or none, and are linked with `lid`. Only compositions documented in an RFC are accepted; anything else is `400` `unsupported-operations`. Each operation runs through the same Action, permission check and approval rules as it would alone, and one `Idempotency-Key` covers the request. Where a resource can only be created this way, its plain create endpoint does not exist.

## Consequences

- The API stays JSON:API, using the specification's own extension.
- Clients need to send and accept a media type parameter, and read results by position.
- Each composition is a deliberate design with its own validation, not a general transaction facility.

## Alternatives

- **Write-only nested attributes** (`first_post` on a thread). Simpler for clients, and a resource with no type, no `lid` and no place for its own metadata.
- **Any combination of operations.** General, and every combination would need rules for what partially valid means.
