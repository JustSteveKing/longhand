# ADR 0054: Stream access is revoked with a stream.revoked event that every client must obey

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0010

## Context

A Mercure hub cannot revoke a connection it has already accepted. A member removed from a space, deactivated, or an agent whose allow-list narrows, would otherwise keep receiving events on their old grant until the connection ended.

## Decision

Whenever a member's stream access changes, Longhand publishes `stream.revoked` to their personal topic. Every client must close its connection and reconnect with a new credential, which reflects the change at once; Longhand's own clients do so immediately. Private spaces, direct spaces and guests are only ever published to personal topics, so Longhand simply stops publishing to a removed member and the revocation holds whatever the client does. For `workspace` spaces, the hub closes every connection after 10 minutes and a reconnect needs a current credential, which bounds what a client ignoring the event could still receive to 10 minutes of content any member could have opened.

## Consequences

- Revocation is instant for well-behaved clients, and for private content regardless of the client.
- A removal made in error costs only a reconnect: the new credential carries the restored access.
- Every client reconnects at least every 10 minutes, which the hub's replay makes seamless.

## Alternatives

- **A reauthenticate hint with no obligation.** The spec's shape, and no guarantee.
- **Personal topics for every space.** Closes the last window, at a publish per member for every event.
