# ADR 0011: If-Match is required on PATCH and DELETE

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0002

## Context

Two people editing the same thread, or a person and an agent changing the same request, can silently overwrite each other. ETags and `If-Match` prevent it, but only when the client sends `If-Match`. HTTP has `428 Precondition Required` (RFC 6585) for a server that requires it.

## Decision

Every mutable resource returns an `ETag`. Every `PATCH` and `DELETE` of a mutable resource must send `If-Match`: missing is `428` `precondition-required`, stale is `412` `precondition-failed`. Endpoints that only append (creating a post, reacting) do not take it.

## Consequences

- No lost updates, for any resource, without anyone having to decide which resources need protecting.
- State changes are `PATCH`es (ADR 0013), so every transition is protected too.
- A client must fetch before it writes, or keep the ETag from its last read. That is one more header, and one more round trip for a client that had nothing cached.
- apiguide.dev needed a `precondition-required` page, which now exists.

## Alternatives

- **`If-Match` optional.** Clients that skip it lose updates silently, which is most clients.
- **Required only where conflicts are likely.** A second rule and a judgement call for every new resource.
