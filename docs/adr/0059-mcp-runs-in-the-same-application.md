# ADR 0059: MCP runs in the same application as REST and the web app, with one Passport authorisation server

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0011

## Context

The spec ran the MCP server as its own Laravel application sharing the domain layer. Every surface must call the same Actions (ADR 0003, ADR 0017), and two applications sharing one domain means two deployments of code only one of them owns.

## Decision

The MCP server is served by the same application, with `laravel/mcp` over Streamable HTTP, on its own route domain. Laravel Passport is the one OAuth 2.1 authorisation server for REST and MCP. Longhand defines its own `.well-known/oauth-protected-resource` and authorisation server metadata, listing the scopes from RFC 0003, instead of the package's single `mcp:use` scope, and allows dynamic client registration for MCP clients.

## Consequences

- One deployment, one copy of the domain, one token store.
- Surfaces are separated by route domain and middleware rather than by process.
- Passport becomes a dependency.

## Alternatives

- **A separate MCP application.** Two deployments of one domain.
- **The package's default discovery routes.** A scope that says nothing about what a token may do.
