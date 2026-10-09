# ADR 0003: The web app calls the domain through Inertia, not the public API

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0001

## Context

Longhand has a public API (REST with OAuth 2.1 tokens bound to one
workspace), an MCP server, and webhooks. It also has a first-party web
app: the React client from the Laravel starter kit, which talks to Laravel
through Inertia with session authentication.

The web app could reach the domain in two ways. It could be a client of
the public API, holding a token like any third party. Or it could have its
own Inertia controllers that call the domain directly.

The reference implementation already puts every use case in one Action,
called by both the REST controller and the MCP tool, so that the two
surfaces cannot drift on validation or permissions.

## Decision

The web app has its own Inertia controllers, using the starter kit's
session authentication. Each controller calls the same Action as the REST
controller and the MCP tool for that use case. The web app does not call
the public API and holds no OAuth token.

## Consequences

- One rule for every caller: whatever the domain forbids, the web app
  cannot do either, because it goes through the same Actions.
- The web app gets everything Inertia and sessions give a first-party
  client: server-side routing, shared props, and no token handling in the
  browser.
- The public API is not exercised by Longhand's own client, so it needs its
  own feature tests and its own consumers to stay honest. Nothing in daily
  use will notice a broken endpoint.
- Every use case has up to three thin adapters (an Inertia controller, a
  REST controller, an MCP tool) in front of one Action. That is
  deliberate, and the reason the Actions must hold all of the logic.
- Real-time updates in the browser still come from the event stream, so
  the web app needs a stream credential. The spec's stream cookie, issued
  to the first-party client, covers this.

## Alternatives

- **The web app as an API client.** It would make Longhand its own first
  API consumer, which keeps the API honest, but it moves token handling
  into the browser and loses what Inertia gives a first-party app.
- **A mix: Inertia for pages, the API for some actions.** Two ways to do
  the same thing from one client, and a permanent question about which to
  use for each new feature.
