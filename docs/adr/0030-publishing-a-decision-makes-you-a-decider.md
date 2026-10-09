# ADR 0030: Whoever publishes a decision is one of the people who decided it

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0005

## Context

Agents can draft decisions but never publish them (ADR 0016). A human publishes the draft, and the record should say who stands behind it. A decision published by someone who is not named among its deciders has nobody accountable for its accuracy.

## Decision

Publishing a decision, as action `decision.publish`, adds the publisher to `decided_by` if they are not already in it, and records them as `published_by`. `decided_by` only ever holds humans. A decision and its post are published together.

## Consequences

- Every active decision names at least one person who vouched for it.
- An agent's proposal never becomes a decision without a person's name on it.
- A scribe recording a decision made by others is listed as deciding it too; they can name the others alongside themselves.

## Alternatives

- **Publishing without joining `decided_by`.** Lets a decision be put on the record by someone who does not stand behind it.
