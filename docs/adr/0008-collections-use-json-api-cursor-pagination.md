# ADR 0008: Collections use JSON:API cursor pagination

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0002

## Context

Conversations change constantly while someone pages through them. JSON:API reserves the `page` parameter family but leaves the strategy open, and publishes a cursor pagination profile.

## Decision

Every collection is paginated with the JSON:API cursor pagination profile: `page[size]` (default 50, maximum 200), `page[after]` and `page[before]`, with `next` and `prev` links. Cursors are opaque and bound to the query that produced them. Every collection has a documented default order, and the ULID is always the final sort key.

## Consequences

- Pages never skip or repeat items when rows are added during paging.
- Any client that implements the profile works unchanged.
- There is no jumping to page 7, and no total count by default.
- The profile's oversized-page error uses `meta.page.maxSize`, the one camelCase member name in an otherwise `snake_case` API, because the profile defines it.

## Alternatives

- **Offset pagination.** Familiar, and wrong for data that changes while you read it.
- **A homemade cursor scheme.** Works, but every client would have to learn it.
