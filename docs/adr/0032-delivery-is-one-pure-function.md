# ADR 0032: When something reaches a member is worked out by one pure function

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0006

## Context

Three places need to know when something will reach a member: the delivery summary returned to a sender, the `lands_at` times on a member's availability, and the scheduler that actually delivers. If they are computed separately, the sender is told one time and the item arrives at another.

## Decision

Delivery is one pure function: the recipient's availability and away periods, the tier, the space's cap and the current time in; a `deliver_at` and a reason out. It checks, in order, access, muting, incident override, away, then the tier's own rule. The delivery summary, `lands_at` and the scheduler all call it, and so does rescheduling when availability changes.

## Consequences

- What a sender is told is what happens.
- The rules can be tested exhaustively without a database or a clock.
- Anything that needs I/O (who can see the thread, who muted what) is looked up before the call and passed in.

## Alternatives

- **Separate calculations per use.** Three copies of the rules that drift.
