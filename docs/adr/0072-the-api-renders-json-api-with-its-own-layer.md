# ADR 0072: The REST API renders JSON:API with a small layer of its own, not Laravel's JsonApiResource

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** none; building RFC 0002 and RFC 0013's REST surface

## Context

RFC 0013 planned JSON:API resources on Laravel 13's `JsonApiResource`. Building the API showed it does not fit RFC 0002. It builds relationship linkage from relations a request asks to include, where RFC 0002 always carries linkage. It loads included resources through Eloquent relations, where ADR 0012 needs every included resource to come from a query that only returns what the caller can see. And it covers none of the rest: error objects, content negotiation, the cursor pagination profile, strict query parameters or atomic operations.

## Decision

The REST surface has its own small JSON:API layer in `app/Http/Api/JsonApi`: a `Serializer` per resource type, which always renders every attribute and its relationship linkage and declares include loaders that only return visible resources; `Document`, for response documents with `jsonapi`, `included` and `meta.request_id`; `QueryParameters`, which refuses anything an endpoint does not declare; `CursorPage`, the cursor profile over Laravel's cursor pagination, with cursors bound to their query; `Versions`, for ETags and `If-Match`; `RequestDocument`, for request documents and field pointers; and `ErrorRenderer`, which turns every exception under `/v1` into error objects from one `ErrorCode` enum. A test checks every serializer's attributes and relationships against `api/openapi.yaml`.

## Consequences

- RFC 0002 holds exactly, including linkage, visibility in includes and the pagination profile.
- The layer is ours to maintain, a few hundred lines that every later context reuses.
- The contract and the serializers cannot drift apart without a test failing.

## Alternatives

- **`JsonApiResource`, extended.** Overriding how it resolves relationships and includes leaves little of it, and its include loading is the part that must change.
- **A JSON:API package.** More features than v1 needs, and its own conventions to reconcile with RFC 0002.
