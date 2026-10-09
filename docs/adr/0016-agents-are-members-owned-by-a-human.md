# ADR 0016: Agents are members owned by a human, and can never exceed their owner

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0003

## Context

An agent can act faster and more often than any person. An agent with a broad token, no owner, or the ability to widen its own access is unaccountable.

## Decision

An agent is a member with `kind: "agent"` and a human owner. Its scopes cannot exceed its owner's role, its spaces must be ones the owner can see, and it only sees the spaces on its allow-list. It posts drafts by default, never publishes a decision, and can never manage identity: agents, scopes, approval rules, invitations or roles. If its owner is deactivated it is suspended. A workspace caps how many agents one member owns.

## Consequences

- Every agent action traces to a person who answers for it.
- Transferring an agent rechecks its scopes and spaces against the new owner, and drops what the new owner could not grant.
- Identity management is human only, on REST or in the web app, never over MCP.

## Alternatives

- **Agents as API keys or integrations.** A second permission model, which is where an agent ends up able to do what a person in the same role could not.
- **Agents with their own roles.** Lets an agent outrank the person responsible for it.
