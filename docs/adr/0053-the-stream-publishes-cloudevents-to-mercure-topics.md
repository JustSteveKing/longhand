# ADR 0053: The stream publishes CloudEvents to Mercure directly, with permissions encoded in its topics

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0010

## Context

The stream is served by a Mercure hub, so PHP holds no connections. Laravel 13's `mercure` broadcast driver wraps payloads in Echo's envelope, not the CloudEvents wire format the API promises. And a hub delivers one update to everyone authorised for its topic, with no per-subscriber filtering by type or rendering.

## Decision

Outbox workers publish each CloudEvent to the hub through `symfony/mercure`'s `HubInterface`, with the event type as the SSE `event`. Topics carry the permissions: shared per-space topics, split by resource family, only for `workspace` spaces and only granted to non-guest members; workspace topics for workspace-wide events; and a personal topic per member for everything else, including every event in private and direct spaces, every event a guest receives, and anything that looks different to different members. A subscriber's grant lists exactly the topics their role, scopes and allow-list permit.

## Consequences

- The wire format is the API's, not Echo's.
- An agent is never granted a topic its scopes do not cover.
- Events in private and direct spaces are published once per member, which costs more and keeps those spaces exact.

## Alternatives

- **Laravel's broadcast driver.** An Echo-shaped stream.
- **One topic per space for everything.** Cannot render per recipient or filter by scope.
- **One topic per member for everything.** Exact, and a publish per member for every event in every space.
