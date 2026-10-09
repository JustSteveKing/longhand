# ADR 0031: Only an active decision can be superseded, so the chain never forks

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0005

## Context

Decisions are immutable and replaced by superseding them. If two people supersede the same decision, there are two current answers to one question, and the log can no longer say which applies.

## Decision

A new decision can only supersede an `active` one. Superseding a decision that is already superseded is `409` `invalid-transition`, with its `superseded_by` in `meta`, pointing the caller at the current decision. The original becomes `superseded` in the same change that publishes its replacement, and its thread gets a linking `fyi` post even when the thread is closed. `GET /v1/decisions/{decision}/chain` returns the whole chain from any link in it.

## Consequences

- Every chain is a straight line with one current decision at the end.
- Someone superseding an old answer is sent to the current one, and has to engage with it.

## Alternatives

- **Allowing forks.** Two "current" decisions, and a log that needs a human to untangle it.
