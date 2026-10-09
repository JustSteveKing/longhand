# ADR 0064: Domain code lives in `src/` under `Longhand\`, in eight contexts that meet only through Actions, queries and events

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0013

## Context

Every rule in the RFCs has to have one home, or a surface or a neighbouring context will bypass it. Laravel's default layout puts everything in `app/`, where nothing stops a controller or another context reaching into any model.

## Decision

Domain code and its Actions live in `src/`, autoloaded as `Longhand\`, in eight bounded contexts: Identity, Conversations, Commitments, Attention, Briefs, CheckIns, Search and Integration. `app/` holds only the surfaces: web, REST, MCP and console. Contexts use each other only through their public `Features/` (Actions), `Queries/` and `Events/`, never through each other's models or tables. `src` never depends on `App`, HTTP, Inertia or `laravel/mcp`. Pest architecture tests enforce each rule.

## Consequences

- A context can be read, tested and changed on its own.
- Cross-context needs become explicit public Actions or queries, which is more code and less coupling.
- Search becomes its own context, with its own tables.

## Alternatives

- **Everything in `app/`.** Nothing enforces a boundary.
- **Separate packages per context.** Stronger walls, and a monorepo's tooling for one application.
