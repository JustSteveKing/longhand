# ADR 0036: Others see a limited view of a member's availability, and agents have none of their own

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0006

## Context

Colleagues need to know whether someone is working and when a message would reach them. They do not need, and should not have, the person's exact hours and focus blocks.

## Decision

Availability is one resource type. To anyone but its member it has `meta.access: "limited"` and only the timezone, response expectation, whether they are in a working window, when the next starts, `lands_at` per tier, and away periods; exact hours, focus blocks, batches and email settings are left out. This follows ADR 0025's pattern of one type with what the caller may see. Agents have no availability of their own: they are always in a window, and everything reaches them immediately.

## Consequences

- Senders can plan around someone without seeing their calendar.
- Clients handle two shapes of the same type, marked by `meta.access`.
- No agent ever has its delivery held, so work assigned to an agent starts at once.

## Alternatives

- **Separate public and private resources.** Two types for one thing.
- **Working hours for agents.** Holds work for no one's benefit.
