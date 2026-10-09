# ADR 0034: Scheduled inbox items are invisible to their recipient, and are rescheduled when availability changes

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0006

## Context

Holding an item until someone's working hours only protects them if they cannot see it early. And a delivery time computed once goes stale when the person changes their hours or comes back from holiday early.

## Decision

An inbox item starts `scheduled` and is invisible to its member until `deliver_at`, when it becomes `open` and `inbox.item_delivered` fires. The sender knows when it will land; the recipient is not shown it sooner. When a member's availability or away periods change, every scheduled item of theirs is worked out again under the new rules. A snoozed item returns exactly at the time the member chose, without going through their availability again.

## Consequences

- A person working outside their own hours does not see work waiting for their next window, by design.
- Rescheduling is a batch job triggered by availability changes.

## Alternatives

- **Show scheduled items, marked as not yet due.** Undoes the point.
- **Fix `deliver_at` at send time.** Simpler, and wrong for anyone whose plans change.
