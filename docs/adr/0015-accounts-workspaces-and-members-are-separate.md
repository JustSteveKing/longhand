# ADR 0015: Accounts, workspaces and members are separate, and the API knows only members

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0003

## Context

People belong to more than one team, and agents belong to exactly one. Tokens are bound to a workspace (RFC 0002), so anything the API exposes has to be answerable as "who is this, in this workspace".

## Decision

An account is a person's login, and belongs to no workspace. A member is an account in a workspace, or an agent, with its own role, handle, timezone and availability. The API exposes members and never accounts; accounts live in the web app.

## Consequences

- One login works across workspaces, with a switcher in the web app.
- Every API caller and every author is a member, so people and agents share one permission model.
- Deleting an account means deactivating one member per workspace, and what is left of a person is per workspace.

## Alternatives

- **One account per workspace.** Simpler, and a separate login for every client a person works with.
- **Accounts in the API.** Would leak a person's other workspaces into a token that is meant to see one.
