# ADR 0013: State changes are PATCHes, and actions that produce a resource are creates

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0002

## Context

The spec has about twenty action endpoints: `/resolve`, `/accept`, `/publish` and the rest. JSON:API defines only create, update and delete. An action endpoint outside JSON:API would make part of the API not JSON:API, which defeats ADR 0004.

## Decision

A transition whose data belongs to the resource is a `PATCH` of its state: resolving a thread is `PATCH /v1/threads/{thread}` with `"status": "resolved"` and an `outcome`; accepting a request is a `PATCH` of its `state`; publishing a draft is a `PATCH` of its `status`. The server validates each transition and who may make it, and answers `409` `invalid-transition` otherwise. An action that produces a new resource is a create: superseding a decision creates a new decision with a `supersedes` relationship. The spec's action URLs do not exist. Each RFC lists its transitions.

## Consequences

- The whole API stays JSON:API, and `If-Match` protects every transition.
- Fewer endpoints, and no question of which one a new action goes on.
- One `PATCH` endpoint now carries several behaviours, each with its own permissions and required fields. The rules move from routing into the domain, where the Actions already enforce them, and each RFC must document every transition explicitly.
- Scopes can no longer be checked per route alone, since one route performs different actions. RFC 0003 has to account for that.

## Alternatives

- **Actions as resources** (`POST /threads/{id}/resolutions`). Pure JSON:API with an endpoint per action, at the cost of a made-up resource type for each.
- **Action endpoints outside JSON:API.** The spec's URLs, and a contract that is only partly JSON:API.
