# ADR 0020: Callers authenticate per surface, with OAuth 2.1 for everything outside the web app

- **Status:** Superseded by ADR 0060
- **Date:** 2026-10-08
- **From:** RFC 0003

## Context

Longhand has four kinds of caller: the web app, third-party apps acting for a person, agents, and MCP clients. Each needs a token bound to one member, and therefore one workspace.

## Decision

The web app uses the starter kit's session (ADR 0003). Third-party apps use OAuth 2.1 authorisation code with PKCE, with the workspace chosen at consent. Agents use OAuth 2.1 client credentials, issued when the agent is created and shown once. MCP clients follow the MCP authorisation spec. Access tokens last an hour; refresh tokens rotate on every use. People sign in with email and password only in v1.

## Consequences

- No long-lived API keys anywhere.
- Consent, client and token management are web app pages, and admins can revoke any token in the workspace.
- Adding passkeys, social login or single sign-on later changes how a person signs in to the web app, not how the API authenticates.

## Alternatives

- **Personal access tokens.** Simple, long-lived, and easily pasted somewhere they should not be.
- **The web app as an OAuth client of the API.** Rejected with ADR 0003.
