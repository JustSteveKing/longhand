# ADR 0056: MCP lists only the tools a token can use, and has no administrative tools at all

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0011

## Context

A model that sees a tool will try to call it. And an agent that could widen its own permissions through the channel it works through would make the permission model decorative.

## Decision

Each tool's `shouldRegister` checks the token, so `tools/list` holds only tools its scopes allow; role, visibility and approval rules are checked when a tool is called. No tool, resource or prompt exists for managing identity, webhooks or stream credentials, changing availability, reading the audit log, changing workspace settings, or deleting anything other than discarding the caller's own drafts. Those stay on REST and in the web app with a human's credentials. A refusal is a tool result with `isError: true`, carrying the JSON:API error object and a sentence saying what to do instead.

## Consequences

- Models see a list they can use, and learn why when a call is refused.
- The rule that agents cannot manage identity is structural on MCP, not only a scope check.

## Alternatives

- **List everything, refuse on call.** More refusals and more confused agents.
- **Administrative tools gated by scopes.** One misconfigured grant away from an agent managing its own permissions.
