# ADR 0002: The product is Longhand, and event types are prefixed `longhand.`

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0001

## Context

The spec was written before the product had a name, so it used
placeholders: the `Async\` root namespace, `api.example.dev` and
`mcp.example.dev`, the `dev.example.` event prefix and the `async://` MCP
resource scheme. The product is now called Longhand, and it has no domain.

Some of these names are cheap to change and some are not. A namespace or a
documentation URL can change in an afternoon. An event `type` is written
into every consumer's code, and a problem `type` URL is promised to be
permanent.

## Decision

- The root namespace is `Longhand\`.
- Documentation uses the reserved `.example` domain:
  `https://api.longhand.example/v1`, `https://mcp.longhand.example/v1`,
  and `https://api.longhand.example/problems/{slug}`.
- Event types are prefixed `longhand.`, for example
  `longhand.request.completed`, and keep that prefix after a domain is
  bought.
- MCP resources use the `longhand://` scheme.
- Product-specific problem `type` URLs are not published until Longhand
  has a real domain.

## Consequences

- Nothing in the contract depends on a domain Longhand does not own.
- The event prefix never has to change, so a domain purchase, or a later
  change of domain, breaks no consumer.
- `longhand.` is not the reverse-DNS prefix CloudEvents recommends. Within
  one product's event stream that costs nothing in practice, but a
  consumer mixing events from many sources has to know the prefix is a
  product name, not a domain.
- The API cannot be made public until there is a domain, because the
  product-specific problem types need permanent URLs.

## Alternatives

- **Buy a domain first.** It would let every name be final now, but it
  holds up the specification for a decision that does not affect the
  design.
- **A reverse-DNS prefix from a future domain.** Correct by the
  CloudEvents recommendation, but it either waits for the domain or
  guesses it, and a wrong guess is a breaking change.
