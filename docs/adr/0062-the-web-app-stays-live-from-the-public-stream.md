# ADR 0062: The web app stays live from the public CloudEvents stream, with Inertia partial reloads

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0012

## Context

Open pages need to update without polling. Laravel's `mercure` broadcast driver with Echo would work, and would add a second set of channels, payloads and authorisation for the same events the stream already carries with its permissions (ADR 0053).

## Decision

The web app opens one `EventSource` per tab on the public stream, with the first-party hub cookie, renewed at least every 10 minutes and on `stream.revoked`. Each event type maps to the page props it affects, and the client asks Inertia for a partial reload of those props. Only `inbox.item_delivered` notifies, with a toast and, if the person allowed it from the inbox, a browser notification.

## Consequences

- One real-time path for every client, the web app included, under the same permissions.
- The server renders every update, so screens have no second rendering path to keep in step.
- An event costs a partial reload rather than a local state change.

## Alternatives

- **Echo and Laravel's `mercure` driver.** A second event channel.
- **Applying event payloads to client state.** Faster, and every screen re-implements the server's rendering.
