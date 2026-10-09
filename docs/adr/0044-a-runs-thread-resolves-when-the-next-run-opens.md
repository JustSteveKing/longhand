# ADR 0044: A check-in run's thread resolves when the next run opens

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0008

## Context

Every run creates a thread. Resolving it as soon as the run closes would end the discussion of its blockers; leaving it open would leave a daily standup with a trail of threads going stale.

## Decision

A run's thread stays open after the run closes, so blockers and answers can be discussed, and resolves itself when the next run of the same check-in opens, with an outcome stating the run's counts. A thread with open requests at that point stays open, and its owner is told in the usual way. When the run closes, the thread is rolled up (ADR 0040), and that roll-up does not count against anyone's daily brief limit.

## Consequences

- A recurring check-in always has exactly one live thread.
- Discussion of a blocker has until the next run to finish, or moves to a thread of its own.

## Alternatives

- **Resolve on close.** Cuts off discussion of the run's blockers.
- **Never resolve.** A stale thread per run.
