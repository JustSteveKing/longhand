# ADR 0039: A brief is a snapshot that becomes stale, never one that is updated in place

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0007

## Context

What a brief cites can change after it is written: a post edited or deleted, a decision superseded, a request completed, a reader's access lost. A brief that silently changed would leave the reader unsure whether what they read is what is there.

## Decision

A ready brief never changes. When something it cites is edited, deleted, superseded or changes state, it becomes `stale` with `stale_since`, and `brief.stale` fires once. When the reader loses access to a cited resource, that citation is hidden (ADR 0012), an item with no visible citations is hidden, and the brief becomes stale. New activity alone does not make a brief stale.

## Consequences

- A reader can always trust that a brief says what it said when it was written, and is told when that is no longer the whole truth.
- Getting an up-to-date brief means asking for another one.

## Alternatives

- **Regenerating on change.** Costly, and a brief that changes under its reader.
- **Ignoring changes.** Briefs that quietly mislead.
