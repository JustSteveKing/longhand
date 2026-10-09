# ADR 0061: The web app keeps REST's guarantees itself

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0012

## Context

The web app calls the domain through Inertia, not the public API (ADR 0003), so it does not pass through the API middleware that gives REST idempotency keys, version checks, typed errors and scope checks.

## Decision

Web controllers validate input, call an Action and render or redirect, with no domain rules of their own; the Action checks permissions, audits and records events. Forms that create send an `Idempotency-Key` made when the form opens, through the same idempotency layer as REST. Edit pages carry the resource's version and send it back, and a stale version is refused and reloaded rather than overwritten. Domain refusals carry their error `code`, shown from one message catalogue keyed by code. Page props come from query classes that apply the same visibility rules as the API, and anything the person cannot see is a `404` page.

## Consequences

- The web app has REST's guarantees without being a REST client.
- Page props are shaped for their screens, not JSON:API documents.
- Error wording is defined once, per code.

## Alternatives

- **Trusting the browser.** Double posts and lost edits.
- **The web app through the API.** Rejected in ADR 0003.
