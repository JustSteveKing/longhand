# ADR 0024: A member's read position in a thread only moves forward, and takes no If-Match

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0004

## Context

Briefs need to know what a member has already seen in each thread. ADR 0011 requires `If-Match` on every `PATCH` of a mutable resource, and a thread's ETag changes every time anyone posts, so a client keeping its place would fail constantly.

## Decision

Each member's read position is the `last_seen_post` relationship on a thread, different for every caller, moved with `PATCH /v1/threads/{thread}/relationships/last_seen_post`. It only moves forward: a post older than the current position is ignored with `200 OK`. Because a stale write can never undo a newer one, the request takes no `If-Match`.

## Consequences

- Clients can update the position freely, from several devices, without conflicts.
- A member cannot move their position backwards to see something as unread again. Nothing in the product needs that.

## Alternatives

- **Requiring `If-Match`.** Correct by the letter of ADR 0011 and unusable in practice.
- **A separate read-position resource.** Another type and prefix for one pointer per thread.
