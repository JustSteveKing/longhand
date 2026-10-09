# ADR 0040: Anyone who can see a thread can roll it up into one summary for everyone

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0007

## Context

Long threads are where async teams lose people. Briefs are per reader, built from each reader's view, but a thread looks the same to everyone who can see it, so one summary can serve them all.

## Decision

Any member who can see a thread can roll it up: a brief with `scope: "thread"`, `audience: "thread"` and no reader, generated from the thread as any member of its space sees it, without drafts. Everyone who can see the thread can read it. The thread's `summary` relationship points at its latest ready roll-up, which clients show first, with the full thread a click away. One roll-up runs at a time per thread, it notifies nobody, and it counts against the daily brief limit of whoever asked. Check-ins roll up each run's thread when it closes.

## Consequences

- A thread can be caught up on in one read, by anyone, without each reader paying for their own brief.
- A thread's summary can fall behind; `meta.posts_since_summary` says by how much.

## Alternatives

- **Posting the summary into the thread.** Puts it at the bottom of what it summarises.
- **Per-reader briefs only.** The same summary generated once per person.
