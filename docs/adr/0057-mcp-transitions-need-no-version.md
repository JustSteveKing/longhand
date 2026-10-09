# ADR 0057: On MCP, transitions need no version and content changes do

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0011

## Context

ADR 0011 requires `If-Match` on every change, to prevent lost updates. Models do not keep ETags reliably, so requiring them on every MCP tool would make agents fail most of their transitions.

## Decision

Tools that move a resource between states (accepting a request, snoozing an item, moving a thread to `waiting`) need no version: each is checked against the resource's current state by its lifecycle rules, and one that no longer applies is refused as `invalid-transition`. Tools that change content (`edit_post`, a request's `done_when`) require the `version` returned in `meta.version` by the reading tool; a missing or stale one is refused like a `428` or `412`, with the current resource in the refusal.

## Consequences

- Lost updates are still impossible: transitions are guarded by state, content by version.
- MCP differs from REST in how the guard is expressed, not in what it guarantees.

## Alternatives

- **Versions everywhere.** Correct, and unusable by models.
- **No versions on MCP.** Lost content edits.
