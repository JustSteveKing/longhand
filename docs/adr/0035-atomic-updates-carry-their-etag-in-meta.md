# ADR 0035: Updates inside atomic operations carry their ETag in the operation's meta

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0006

## Context

ADR 0011 requires `If-Match` on every update or delete of a mutable resource. An atomic operations request (ADR 0022) has one set of headers for any number of operations, so a header cannot say which version each one expects. Bulk inbox changes and resolving a decision thread both update resources this way.

## Decision

Each `update` or `remove` operation on a mutable resource carries the ETag it expects in its own `meta.if_match`. A missing one is `428` `precondition-required`, a stale one `412` `precondition-failed`, each with a `source.pointer` to the operation, and either undoes the whole request. An `If-Match` header on an atomic operations request is ignored.

## Consequences

- ADR 0011 holds everywhere, including inside atomic operations.
- Clients keep an ETag per resource they intend to change in bulk.

## Alternatives

- **Exempting resources changed in bulk.** Leaves those resources unprotected everywhere.
- **No version checks inside atomic operations.** Lost updates through the one route that changes several things at once.
