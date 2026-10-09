# ADR 0012: A resource the caller cannot see is 404, never 403

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0002

## Context

Longhand has private spaces, direct conversations between up to eight people, guests who see only what they are added to, and agents restricted to an allow-list of spaces. Answering `403` for a resource someone cannot see confirms that it exists, which leaks, for example, that two colleagues have a direct conversation.

## Decision

A resource the caller cannot see is `404` `resource-not-found`, exactly as if it did not exist. The same applies inside `include`: an invisible related resource is left out of `included`, and its identifier out of the relationship. `403` is reserved for a resource the caller can see but may not change in this way: `insufficient-scope`, or a product-specific error such as `approval-required`.

## Consequences

- Visibility never leaks through error codes or includes.
- A `404` no longer tells a developer whether an ID is wrong or a permission is missing. The error's `detail` cannot say which, for the same reason.

## Alternatives

- **`403` for everything forbidden.** Clearer for debugging, and it leaks existence.
