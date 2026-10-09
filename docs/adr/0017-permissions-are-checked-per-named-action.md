# ADR 0017: Permissions are checked per named action, not per route

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0003

## Context

ADR 0013 makes state changes `PATCH`es, so one route performs several actions, each with its own rules. The web app, REST and MCP all reach the same domain, and must not differ in what they allow.

## Decision

Every write is a named action in the domain, `resource.verb` (`request.assign`, `post.publish`). Each action declares the scope it needs, who may take it, and whether approval rules apply. What a caller may do is the intersection of the token's scopes, the member's role (an agent's owner's role) and what the member can see. The check runs in the Action, so every surface gets the same answer. Action names are what approval rules list, what the audit log records, and what `insufficient-scope` and `approval-required` errors name in `meta.action`.

## Consequences

- One permission check, shared by every surface.
- Each RFC lists its resources' actions with their scope and who may take them.
- Route middleware can only do coarse checks; the real decision is in the domain.

## Alternatives

- **Scopes per route.** Impossible once a route does several things.
- **Checks per surface.** Three implementations that drift.
