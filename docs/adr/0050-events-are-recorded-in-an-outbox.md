# ADR 0050: Events are recorded in an outbox in the same transaction as their change

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0010

## Context

Webhooks, the stream and the events endpoint all promise that an event describes a change that happened. Publishing after the commit can lose an event if the process dies in between; publishing inside the transaction can announce a change that is then rolled back.

## Decision

Every event is written to an `events` outbox table in the same database transaction as the change it describes. Queued workers read the outbox and deliver to webhooks and the stream; the events endpoint reads the same table. Domain code records events and never delivers them. Events are kept for as long as the retention RFC sets, 30 days until it does.

## Consequences

- An event exists if and only if its change was committed.
- Delivery can lag the change by the time a worker takes to pick it up.
- The outbox is the one place every route an event takes starts from.

## Alternatives

- **Publishing after commit.** Loses events on a crash.
- **Publishing inside the transaction.** Announces changes that never happened.
