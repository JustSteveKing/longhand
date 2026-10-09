# ADR 0058: Resolving a thread is always a human action, and agents propose resolutions

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0011

## Context

Resolving a thread ends a conversation and records its outcome for everyone in it. The spec required human confirmation when an agent resolved one, through MCP elicitation, which `laravel/mcp` does not support.

## Decision

No agent resolves a thread, on any surface, whatever its scopes or approval rules, as with publishing a decision (ADR 0016). An agent proposes one with `propose_resolution`, which sets the thread's `proposed_outcome` and `proposed_by` and gives the owner a `draft_awaiting_approval` inbox item. The owner resolves from the proposal or clears it.

## Consequences

- Every outcome on record was accepted by a person.
- Agents still do the work of summarising and closing out; a person makes it final.

## Alternatives

- **Resolution under approval rules.** Lets a workspace allow agents to end conversations for the people in them.
- **Waiting for elicitation.** No mechanism at all in the meantime.
