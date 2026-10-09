# ADR 0028: A request's history is a collection of transitions

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0005

## Context

The spec gave each request a `history` attribute: a list of states, times and member IDs. JSON:API discourages identifiers inside attributes, because they cannot be included and cannot be hidden from a caller who should not see the member (ADR 0012).

## Decision

Every action on a request, whether or not it changes the state, is recorded as a `request_transitions` resource (`rtr_`) with the action, the states before and after, an optional note, who did it and who held the request afterwards. Transitions are listed oldest first at `GET /v1/requests/{request}/transitions` and are never edited.

## Consequences

- The people in a request's history are relationships that can be included, and hidden when they should be.
- A request's resource stays small; its history is paginated like any collection.
- One more type and prefix.

## Alternatives

- **The spec's `history` attribute.** Identifiers in attributes.
- **The audit log.** Already records the same actions, but only owners and admins can read it.
