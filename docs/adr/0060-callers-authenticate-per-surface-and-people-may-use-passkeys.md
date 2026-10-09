# ADR 0060: Callers authenticate per surface, and people sign in with a password or a passkey

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0012
- **Supersedes:** ADR 0020

## Context

ADR 0020 set how each kind of caller authenticates, and said people sign in with email and password only in v1. The starter kit ships Fortify's passkeys and two-factor authentication enabled, and removing them would make accounts weaker for no gain. Everything else in ADR 0020 stands.

## Decision

The web app uses the starter kit's session (ADR 0003). Third-party apps use OAuth 2.1 authorisation code with PKCE, with the workspace chosen at consent. Agents use OAuth 2.1 client credentials, issued when the agent is created and shown once. MCP clients follow the MCP authorisation spec, through the same Passport server (ADR 0059). Access tokens last an hour; refresh tokens rotate on every use. People sign in with email and password or with a passkey, and can add two-factor authentication with an authenticator app and recovery codes. These are account settings, covering every workspace the person belongs to. Magic links, social login and single sign-on are not in v1, nor is requiring two-factor authentication for a workspace.

## Consequences

- No long-lived API keys anywhere.
- Consent, client and token management are web app pages, and admins can revoke any token in the workspace.
- Passkeys and two-factor authentication are supported from the first release, as the starter kit provides them.

## Alternatives

- **Email and password only.** ADR 0020's position, and weaker accounts.
- **Personal access tokens.** Simple, long-lived, and easily pasted somewhere they should not be.
- **The web app as an OAuth client of the API.** Rejected with ADR 0003.
