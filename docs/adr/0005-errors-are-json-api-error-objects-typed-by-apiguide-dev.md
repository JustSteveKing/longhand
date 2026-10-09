# ADR 0005: Errors are JSON:API error objects, typed by the apiguide.dev catalogue

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0002

## Context

RFC 0001 lists RFC 9457 problem details among the standards Longhand uses, and the spec gave every error a permanent `type` URL: the apiguide.dev catalogue for generic HTTP errors, Longhand's own documentation for product-specific ones. ADR 0004 makes the API JSON:API, which defines its own error format, and an API cannot return both. JSON:API 1.1 error objects have a `links.type` member, "a link that identifies the type of error", which carries the same idea as RFC 9457's `type`.

## Decision

Errors are JSON:API error objects in a top-level `errors` array. Every error has `status` (a string), `code` (a stable slug), `title`, `detail` and `links.type`, plus `source` where it applies and documented `meta` members for recovery. Generic errors take their `code` and `links.type` from the apiguide.dev catalogue; product-specific errors use `https://api.longhand.example/problems/{code}`, published once Longhand has a domain (ADR 0002). Validation failures produce one error per field with a `source.pointer`. This replaces RFC 9457 from RFC 0001.

## Consequences

- Clients get the error format their JSON:API library already parses, and every error still has a permanent, documented type.
- A validation failure points at exactly the field that caused it, through `source.pointer`.
- The apiguide.dev catalogue pages show RFC 9457 examples. Their `type` URLs mean the same thing as `links.type`, so they remain the right documentation, but a reader sees a different envelope there than in Longhand.
- RFC 0001's standards list is now partly wrong. It is Accepted and frozen, so this ADR is the correction, and RFC 0002 says so.

## Alternatives

- **RFC 9457 alongside JSON:API documents.** Two error formats in one API, and JSON:API clients would not recognise either reliably.
- **JSON:API errors without `links.type`.** Valid JSON:API, but the error type would live only in `code`, with no documentation a client can open.
