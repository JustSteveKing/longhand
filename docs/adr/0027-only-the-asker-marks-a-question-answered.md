# ADR 0027: Only the asker marks a question answered, and Longhand reminds them

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0004

## Context

Unanswered questions drive inbox items, briefs and check-ins, so "answered" has to be right. The spec counted any reply that referenced a question as its answer, and left open whether agents could decide. Replies are often clarifying questions.

## Decision

A question is answered only when its asker marks it so, optionally pointing at the post that answered it. No reply answers a question by itself. Once a question has a reply from someone else and is still unmarked a working day later, the asker gets a `question_unmarked` inbox item, repeated weekly, at most three times, stopping when the question is marked or the thread closes. Detecting answers automatically is left to its own RFC.

## Consequences

- "Answered" means what the asker says, which is the only reliable signal.
- Some questions will stay unmarked after the reminders stop, and show as unanswered in briefs.

## Alternatives

- **The first reply from someone else answers it.** Automatic, and wrong whenever that reply asks something back.
- **Agents decide.** The answer detection RFC's question, not v1's.
