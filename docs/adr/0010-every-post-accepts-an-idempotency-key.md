# ADR 0010: Every POST accepts an idempotency key

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0002

## Context

Networks fail between a request arriving and its response leaving. Agents and integrations retry. Without a way to make a retry safe, a retried "create request" assigns the same work twice.

## Decision

Every `POST` accepts an `Idempotency-Key` header, following the IETF draft. Keys are scoped to the workspace member, method and path, and kept for 24 hours. A replay with the same body returns the original response with `Idempotent-Replayed: true`. The same key with a different body, or while the first request is in flight, is `409` `idempotency-key-conflict`. Responses with a `5xx` status are not stored, so a failed request can be retried with the same key.

## Consequences

- Any `POST` can be retried without duplicating work, which agents rely on.
- The server stores a response per key for a day, which costs storage and a lookup on every keyed `POST`.
- MCP write tools pass an optional key through to the same mechanism.

## Alternatives

- **Idempotency only where duplicates are costly.** A rule every endpoint author has to apply correctly.
- **Natural keys per resource.** Works for some resources, and there is no natural key for "post a message".
