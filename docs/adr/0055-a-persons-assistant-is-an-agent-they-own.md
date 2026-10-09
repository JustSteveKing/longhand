# ADR 0055: A person's MCP assistant is an agent they own, with its own allowance and spaces that follow them

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0011

## Context

People connect their own assistants, in desktop and IDE clients, through MCP. Connecting as the person would give the assistant their full rights, unlabelled, including publishing decisions and managing members. Treating each client as an ordinary agent would use up the agent cap and leave an allow-list going stale.

## Decision

The first time a person connects a client to a workspace, the consent screen creates an agent they own, marked `assistant: true`, with `acts_on_behalf_of` set to them, named after the client, with the scopes they consent to. Every agent rule applies to it. Assistants have their own allowance, `max_assistants_per_member`, 5 by default, apart from the agent cap. Its allow-list follows the spaces the person can see unless they fix it, and never exceeds them. Revoking the client's tokens suspends it. Nobody connects MCP as themselves.

## Consequences

- An assistant is always labelled ("Claude for Steve") and always accountable to its person.
- Connecting several devices does not crowd out the agents a person builds for others.
- An assistant's access changes with the person's, with nothing to maintain.

## Alternatives

- **Connecting as the person.** Full rights, no label.
- **Assistants as ordinary agents.** Capped with everything else, and fixed allow-lists that go stale.
