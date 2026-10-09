# ADR 0004: The REST API is JSON:API 1.1

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0002

## Context

The spec defined its own response format: a `data` and `meta` envelope around flat resources that reference each other by ID. It left open how a client fetches a thread with its owner and participants without a request per reference, and it is one more bespoke format every client author has to learn. Longhand's API consumers include agents and third-party integrations, most of which will be written with a general-purpose HTTP or JSON:API library.

## Decision

The REST API follows JSON:API 1.1: the `application/vnd.api+json` media type with JSON:API content negotiation, resource objects with `type`, `id`, `attributes` and `relationships`, references as relationships rather than attributes, compound documents through `include` and `included`, and JSON:API's `filter`, `sort` and `page` parameter families. Types are plural and `snake_case` (`threads`, `inbox_items`), and member names are `snake_case`. RFC 0002 holds the detail.

## Consequences

- Any JSON:API client library works against Longhand without Longhand-specific code.
- Related resources come back in one request through `include`, with each one appearing once.
- Every resource example in the spec changes shape, and each later RFC writes its resources as JSON:API resource objects.
- JSON:API defines no custom actions, so state changes need a rule of their own (ADR 0013).
- `snake_case` member names are allowed by JSON:API but not its recommended style; they match the spec and Laravel's conventions.
- Events and webhooks are CloudEvents, not JSON:API documents, so there are two formats in the contract, each standard for its job.

## Alternatives

- **The spec's own format.** Simpler to read, but bespoke, with no answer for related resources beyond a homemade expansion parameter.
- **JSON:API's `include` on the spec's format.** Borrows one feature while producing a format no library supports.
