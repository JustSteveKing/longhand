# ADR 0029: A note about a transition goes in the request document's meta, not in an attribute

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0005

## Context

Many transitions carry a sentence explaining them: why a request was declined, why a thread was reopened, why a date proposal was turned down. Making each one an attribute (`decline_reason`, `reopen_reason` and so on) adds fields to resources that only mean something at the moment of one change, and are overwritten by the next.

## Decision

A note about a transition goes in the request document's top-level `meta.note`, which JSON:API allows in any document. It is stored with the record of that transition (a request transition, the audit log entry) and carried in the event, never on the resource itself. Each RFC says which transitions require a note.

## Consequences

- Resources carry only their current state.
- Every note stays attached to the change it explains, and none is lost to a later change.
- Clients learn one place to put a note, whatever they are changing.

## Alternatives

- **An attribute per kind of note.** A growing list of fields that are each only true for a moment.
- **A generic `status_note` attribute.** One field, overwritten by every change.
