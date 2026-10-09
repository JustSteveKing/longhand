# ADR 0021: The audit log is append-only and records failed attempts

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0003

## Context

Agents act alongside people under delegation and approval rules. Accountability needs a record nobody can rewrite, including of what was refused.

## Decision

Every action, successful or refused, is recorded as an `audit_events` resource with the action name, the actor, any member it acted for, the subject, the scope used, the surface (`rest`, `mcp`, `web` or `system`) and the changes. Entries cannot be edited or deleted by anyone. Owners and admins read it through `GET /v1/audit-events` with `audit:read`, which no agent can hold.

## Consequences

- An agent repeatedly trying something it may not do is visible.
- The log grows without bound until the retention RFC sets rules for it.
- Writing the entry is part of every Action, on every surface.

## Alternatives

- **Successful actions only.** Hides exactly the behaviour worth noticing.
- **Application logs.** Not queryable by the people responsible, and not guaranteed to be kept.
