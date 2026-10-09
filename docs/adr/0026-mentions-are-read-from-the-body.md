# ADR 0026: Mentions are read from the body by the server, and never grant access

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0004

## Context

The spec had clients send a `mentions` list alongside the body. The two can disagree, and agents writing Markdown would forget the list. Mentions also decide who is told about a post, so a mention must not become a way to reach someone who cannot see the thread.

## Decision

The server reads `@handle` mentions from the body, outside code, and sets the read-only `mentions` relationship. Mentioning a member who cannot see the thread does not add them anywhere and does not tell them; their entry in the delivery summary says `no_access`. Mentioning a member who can see it makes them a participant. Editing delivers new mentions only.

## Consequences

- The text and the mentions always agree.
- Adding someone to a conversation is always a deliberate act, never a side effect of typing their name.

## Alternatives

- **A client-supplied list.** Two sources of truth.
- **Mentions add the member to the space.** Convenient, and a quiet way around a private space.
