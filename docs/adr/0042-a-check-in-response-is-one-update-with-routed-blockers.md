# ADR 0042: A check-in response is one update post, with each blocker as a routed question

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0008

## Context

The spec turned each answer into its own post. A team of twelve answering three questions leaves thirty-six posts, with nothing holding one person's update together, and a blocker sits among them until someone reads the thread.

## Decision

A response writes one `update` post, with each `update` answer under its question as a heading, and one `question` post per `blocker` answer, replying to that update. Blockers are routed like any question (ADR 0033): to whoever they mention, otherwise to the thread's owner, who is the check-in's owner. A response can instead say there is nothing to report, which posts nothing and is counted as an answer.

## Consequences

- Each person's standup reads as one update, as it would have been said aloud.
- A blocker is in someone's inbox the moment it is posted.
- Editing an answer is editing a post, with the usual revision rules.

## Alternatives

- **One post per answer.** The spec's shape, and a thread nobody can scan.
- **Blockers as ordinary answers.** They wait for someone to read the thread.
