# ADR 0006: The major version is in the URL path

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0002

## Context

An API needs a way to make breaking changes without breaking existing clients, and a definition of what counts as breaking. The options are a path segment, a header, or a media type parameter.

## Decision

The base URL carries the major version (`/v1`). It changes only for a breaking change, as RFC 0002 defines one: removing or renaming anything, changing a type or meaning, adding or tightening required input, or changing an error's type or status. Additive changes are not breaking, and clients must ignore members and enum values they do not recognise. Deprecation is signalled with the `Deprecation` and `Sunset` headers, and removal only happens in a new major version.

## Consequences

- The version is visible in every log line, link and browser address bar.
- A new major version means a second set of routes, which keeps old clients working and costs maintenance for as long as both run.
- Clients must be written to tolerate additions, which the API relies on to evolve without a new version.

## Alternatives

- **A version header.** Stable URLs, but invisible in logs and links, and easy to forget.
- **A media type parameter.** JSON:API forbids media type parameters other than `ext` and `profile`.
