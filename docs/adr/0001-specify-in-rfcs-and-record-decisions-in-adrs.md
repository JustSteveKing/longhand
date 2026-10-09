# ADR 0001: Specify in RFCs and record decisions in ADRs, before code

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** none

## Context

Longhand is an educational resource: an async, Slack-style app for remote
teams and the agents that work alongside them, built in public as a
teaching series. The product is the vehicle; the lessons are the point.
A lesson that shows the code but not the reasoning behind it teaches the
wrong half.

It is also built by one person with an agent writing much of the code,
which makes undocumented decisions doubly expensive: the reasoning lives
nowhere a later session, or a reader, can find it.

A draft of the whole product already exists, as [the spec](../spec.md). It
is too large to review, build or teach from as one document.

## Decision

We specify Longhand before building it. The spec is the parent document.
Each part that needs its own design becomes an RFC in `docs/rfc/`; each
decision worth remembering becomes an ADR in `docs/adr/`; the API contract
is a hand-written OpenAPI document in `api/`, written from accepted RFCs.
Code implements accepted documents only. When building shows a document
was wrong, the document changes first. The process is in
[docs/README.md](../README.md).

## Consequences

- Every behaviour has a written why, findable by a reader, a person or an
  agent without the conversation that produced it. For a teaching series,
  that is content, not overhead.
- The OpenAPI document can be reviewed and linted before any handler
  exists.
- Slower to first line of code. That is the point for the parts that are
  hard to change later (identity and agents, the attention model, the event
  contract), and a cost to watch for the parts that are not; small changes
  may skip an RFC and go straight to an ADR.
- The documents can drift from the code. The indexes and the rule that
  documents change first are the only defence.

## Alternatives

- **Code first, docs after.** Fastest start; the reasoning is lost by the
  second week, and so is the lesson.
- **Keep the spec as the only document.** Simpler, but a single decision
  cannot then be found, cited, taught or superseded on its own.
