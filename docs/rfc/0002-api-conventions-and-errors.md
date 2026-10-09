# RFC 0002: API conventions and errors

- **Status:** Accepted
- **Created:** 2026-10-08
- **Depends on:** RFC 0001, ADR 0002
- **Amended by:** RFC 0003, RFC 0004, RFC 0005, RFC 0006, RFC 0007, RFC 0008

## Summary

The Longhand REST API is a JSON:API 1.1 API. Every endpoint follows the
same conventions for documents, identifiers, relationships and includes,
filtering, sorting, pagination, idempotency, concurrency, long-running
work, rate limits, deprecation and errors, so a client written against
one resource works against all of them, and any JSON:API client library
works against Longhand. Errors are JSON:API error objects whose
`links.type` points at the apiguide.dev errors catalogue for generic HTTP
problems, and at Longhand's own documentation for problems that only
exist here. This RFC is section 2 of [the spec](../spec.md), rebased onto
JSON:API and made complete enough to build from.

## Problem

An API with dozens of resources drifts unless the rules every endpoint
shares are written down once. Without them, one endpoint paginates with
offsets and another with cursors, one embeds related records and another
returns IDs, one returns `404` for a resource the caller cannot see and
another returns `403`. Each inconsistency is small, and every client pays
for all of them.

The spec defined its own conventions: a `data` and `meta` envelope, flat
resources that reference each other by ID, and RFC 9457 errors. That is
one more bespoke format for client authors to learn, and it leaves open
how a client fetches a thread with its owner and participants without a
request per reference. JSON:API already answers both, with an existing
ecosystem of client and server libraries.

Agents are first-class API consumers. They retry, and they read error
responses literally, so an error has to say what went wrong and what to
do next in a form a program can act on.

## Goals

1. One set of conventions for every endpoint, with nothing left to the
   author of a single resource.
2. A standard document format, JSON:API 1.1, so client authors use an
   existing library instead of learning Longhand's.
3. Standards for everything JSON:API leaves open: ULIDs, RFC 3339 and
   ISO 8601, the IETF `Idempotency-Key`, `RateLimit` and `Deprecation`
   drafts, RFC 8594 `Sunset`, RFC 6585 `428`, and the JSON:API cursor
   pagination profile.
4. Every error is actionable: a stable type a program can switch on, a
   documented page a developer can read, a pointer to what caused it, and
   whatever the client needs to recover.
5. Retries are safe, and concurrent edits never silently overwrite each
   other.

## Non-goals

- Authentication and the scope model, which are in RFC 0003. This RFC
  only covers how authentication failures are reported.
- The event envelope and webhook signing, which are in RFC 0010. Events
  are CloudEvents, not JSON:API documents.
- The shape of any individual resource.
- Sparse fieldsets. Every response returns full resources in v1.

## Design

### Versioning

The base URL carries the major version: `https://api.longhand.example/v1`
in documentation (ADR 0002). The major version changes only for a breaking
change, which in this API means any of:

- removing or renaming a resource type, attribute, relationship, endpoint,
  query parameter, enum value or event type;
- changing an attribute's type or meaning, or a relationship's cardinality;
- making an optional request field required, or adding a new required one;
- tightening validation so a previously valid request fails;
- changing an error's type, or the status an existing error returns.

Adding endpoints, optional request fields, attributes, relationships, enum
values in responses, includable paths, event types and error types is not
breaking. Clients must ignore members and enum values they do not
recognise.

### Media type

Requests and responses use the JSON:API media type,
`application/vnd.api+json`, with JSON:API's content negotiation rules:

- A request body must be sent with `Content-Type: application/vnd.api+json`.
  Any media type parameter other than `ext` or `profile`, or an extension
  Longhand does not support, is `415`.
- `Accept` must allow `application/vnd.api+json`. If every JSON:API entry
  in `Accept` is disqualified, the response is `406`.
- Longhand applies the cursor pagination profile on collections and
  announces it in the response `Content-Type` `profile` parameter.
- Every response document carries `"jsonapi": { "version": "1.1" }`.

The two endpoints that are not JSON:API are the event stream
(`text/event-stream`, RFC 0010) and file upload targets (RFC 0004).

### Documents and resource objects

A resource is returned as a JSON:API resource object:

```json
{
  "jsonapi": { "version": "1.1" },
  "data": {
    "type": "threads",
    "id": "thr_01JA9X...",
    "attributes": {
      "title": "Pick a queue driver for the billing service",
      "purpose": "decision",
      "status": "open",
      "decide_by": "2026-10-15T17:00:00Z",
      "outcome": null,
      "last_activity_at": "2026-10-08T11:40:00Z",
      "created_at": "2026-10-06T09:12:00Z"
    },
    "relationships": {
      "space": { "data": { "type": "spaces", "id": "spc_01JA9S..." } },
      "owner": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
      "participants": {
        "data": [
          { "type": "members", "id": "mem_01JA7Q..." },
          { "type": "members", "id": "mem_01JA7R..." }
        ]
      }
    },
    "links": { "self": "https://api.longhand.example/v1/threads/thr_01JA9X..." }
  },
  "meta": { "request_id": "7c0e5a52-3a4f..." }
}
```

- **Types** are plural and `snake_case`: `members`, `spaces`, `threads`,
  `posts`, `requests`, `decisions`, `inbox_items`, `briefs`, `check_ins`,
  `check_in_runs`, `subscriptions`, `uploads`, `invitations`,
  `audit_events`.
- **Attribute and relationship names** are `snake_case`. JSON:API permits
  underscores inside member names and recommends no particular casing;
  `snake_case` matches the spec and Laravel's native conventions.
- **Every attribute is always present,** with `null` for no value, so a
  client can tell "no value" from "not sent". Request documents may omit
  optional attributes.
- **References to other resources are relationships, never attributes.**
  The spec's flat fields (`"owner": "mem_..."`) become relationships with
  resource identifier objects.
- **Values that are not resources,** such as a post's `body`, a thread's
  `counts` or a member's public availability summary, stay as structured
  attributes.
- Every resource object has a `links.self`. Relationship `links` are not
  provided in v1.

A collection's primary data is an array of resource objects. A document
with no primary data, for example a deleted resource, is `204 No Content`.

### Identifiers

Resource `id`s are ULIDs with a type prefix, so IDs sort by creation time
and say what they identify even outside a resource object:

| Prefix | Type | | Prefix | Type |
| --- | --- | --- | --- | --- |
| `wsp_` | workspaces | | `brf_` | briefs |
| `mem_` | members (human or agent) | | `chk_` | check_ins |
| `spc_` | spaces | | `run_` | check_in_runs |
| `thr_` | threads | | `sub_` | subscriptions |
| `pst_` | posts | | `dlv_` | webhook deliveries |
| `req_` | requests | | `evt_` | events |
| `dec_` | decisions | | `upl_` | uploads |
| `inb_` | inbox_items | | `stk_` | stream tickets |
| `inv_` | invitations | | `aud_` | audit_events |
| `smb_` | space_memberships | | `rxn_` | reactions |
| `rev_` | post_revisions | | `rtr_` | request_transitions |
| `avl_` | availabilities | | `awy_` | away_periods |
| `dig_` | digests | | `bit_` | brief_items |
| `bfb_` | brief_feedback | | `rsp_` | check_in_responses |

`dlv_`, `stk_`, `inv_` and `aud_` are additions to the spec's list: the
spec uses webhook deliveries, stream tickets and audit events without a
prefix, and invitations are new with onboarding (RFC 0001). `smb_`,
`rxn_` and `rev_` come from RFC 0004, `rtr_` from RFC 0005, and
`avl_`, `awy_` and `dig_` from RFC 0006, and `bit_` and `bfb_` from
RFC 0007, and `rsp_` from RFC 0008. IDs are
generated by the server; client-generated IDs are not accepted. A request
that creates several related resources at once, through Atomic
Operations (ADR 0022), links them with JSON:API's `lid`. IDs are opaque to clients: they may sort them, but must
not parse them.

### Time

Timestamps are RFC 3339 in UTC with a `Z` suffix. Durations are ISO 8601,
for example `PT24H` or `P7D`. Local times inside availability (working
hours, focus blocks) are `HH:MM` wall-clock times interpreted in the
member's IANA timezone, which is always sent alongside them.

### Including related resources

Any endpoint that returns resources supports JSON:API's `include`
parameter. Relationship paths are comma separated, and dotted for nested
relationships:

```http
GET /v1/threads/thr_01JA9X...?include=owner,participants,posts.author
```

Included resources appear once each in the top-level `included` array,
with full linkage: every included resource is reachable from the primary
data through relationships. Each resource type documents the paths it
supports, and a path that is not supported, or `include` on an endpoint
that supports none, is `400`.

`include` never widens what the caller can see. A related resource the
caller cannot see is left out of `included`, and its identifier is left
out of the relationship, exactly as if it did not exist.

### Filtering

Filters use JSON:API's reserved `filter` family, named after the field
they filter: `filter[status]=open,waiting`. Multiple values are comma
separated and match any of them. Different filters combine with AND.
Search's query text is `filter[q]`.

Each collection documents its filters. An unknown filter, or an invalid
filter value, is `400`, with `source.parameter` naming it.

### Sorting

Collections that allow a choice of order support JSON:API's `sort`
parameter: `sort=-last_activity_at`, with a leading `-` for descending and
commas for multiple keys. Every collection has a documented default order
used when `sort` is absent. An unsupported sort field is `400`.

Whatever the requested order, the ULID is always the final sort key, so
items with the same value never swap between pages.

### Pagination

Collections are paginated with the
[JSON:API cursor pagination profile](https://jsonapi.org/profiles/ethanresnick/cursor-pagination/):

- `page[size]` sets the page size: default 50, maximum 200.
- `page[after]` and `page[before]` take a cursor from a previous page.
- The top-level `links` carry `next` and `prev`, `null` at either end.

A cursor is opaque and bound to the query that produced it. A cursor that
is unknown, expired, or reused with different filters or sort is `400`
`invalid-pagination-cursor`, with `source.parameter` naming `page[after]`
or `page[before]`.

A `page[size]` over the maximum is `400` with the profile's own error: its
`links.type` is
`https://jsonapi.org/profiles/ethanresnick/cursor-pagination/max-size-exceeded`,
`source.parameter` is `page[size]`, and `meta.page.maxSize` carries the
limit. `maxSize` is camelCase because the profile defines it, the one
member name in the API that is not `snake_case`.

### Query parameters

The only query parameters are JSON:API's (`include`, `filter[...]`,
`sort`, `page[...]`) and Longhand-specific ones documented per endpoint.
Longhand-specific names contain at least one character outside `a-z`, as
JSON:API requires, so they can never collide with a future JSON:API
parameter: an underscore is enough.

Any other query parameter, including `fields[...]` while sparse fieldsets
are unsupported, is `400`, with `source.parameter` naming it. Silently
ignoring a misspelt parameter returns the wrong data with a `200`, which
is worse than an error, and JSON:API requires the `400` in any case.

### Writing: create, update, delete

- **Create** is `POST` to a collection with a document whose `data` is a
  resource object without an `id`. The response is `201 Created` with the
  new resource and a `Location` header.
- **Update** is `PATCH` to a resource with a document whose `data` has the
  resource's `type` and `id` and only the attributes and relationships
  being changed. The response is `200 OK` with the full resource.
- **Delete** is `DELETE` to a resource. The response is `204 No Content`.

Where the spec creates a resource together with a first child (a thread
with its first post, for example), the request document carries both, and
JSON:API's `lid` links them. Each RFC defines those documents.

### State changes

JSON:API defines create, update and delete, and nothing else, so the
spec's action endpoints (`/resolve`, `/accept`, `/publish` and the rest)
are expressed in those terms:

- **A transition whose data belongs to the resource is a `PATCH`.**
  Resolving a thread is `PATCH /v1/threads/{thread}` with
  `"status": "resolved"` and an `outcome`; accepting a request is
  `PATCH /v1/requests/{request}` with `"state": "accepted"`; publishing a
  draft is `PATCH /v1/posts/{post}` with `"status": "published"`. The
  server checks each transition against the resource's lifecycle and who
  may make it, and answers `409` `invalid-transition` otherwise. `If-Match`
  applies as it does to any other `PATCH`.
- **An action that produces a new resource is a create.** Superseding a
  decision creates a new decision whose `supersedes` relationship points
  at the old one; resolving a decision thread with its decision creates
  the decision alongside the transition, linked by `lid`.

Each RFC lists its transitions: the attribute that changes, the values it
may move between, the extra attributes each move needs, and who may make
it. The spec's action URLs do not exist.

### Idempotency

Every `POST` accepts an `Idempotency-Key` header, following the IETF
Idempotency-Key draft:

- The key is a client-chosen string of up to 255 characters. A UUID or a
  ULID is recommended.
- A key is scoped to the token's workspace and member, the method and the
  path.
- Keys are kept for 24 hours from the first request.
- A replay with the same key and the same body returns the original
  status, headers and body, plus `Idempotent-Replayed: true`.
- The same key with a different body is `409` `idempotency-key-conflict`.
- The same key while the first request is still being processed is also
  `409` `idempotency-key-conflict`, with `Retry-After`.

Error responses are stored and replayed like any other, apart from `5xx`,
which are not stored, so a client can retry a server failure with the
same key.

### Concurrency

Every mutable resource returns an `ETag` when fetched, created or
updated. A `PATCH` or `DELETE` on a mutable resource must send `If-Match`
with the ETag it last saw:

- No `If-Match` is `428` `precondition-required`, telling the client to
  fetch the resource, read its ETag and retry.
- An `If-Match` that does not match the current ETag is `412`
  `precondition-failed`, with the current ETag in the response so the
  client can refetch and retry.

Requiring it everywhere, rather than only where conflicts are likely,
keeps one rule for every resource, and the cost to a client is reading a
header it already receives. Endpoints that only ever append (creating a
post, reacting) do not take `If-Match`.

### Long-running work

Work that cannot finish within the request (generating a brief, for
example) returns `202 Accepted`. JSON:API sets the status code and leaves
the rest open, so Longhand adds: a `Location` header pointing at the
resource to poll, a `status` attribute on that resource, and an event when
it reaches a final state.

### Rate limits

Every response carries the IETF `RateLimit-Policy` and `RateLimit`
headers. Limits apply per token. A request over the limit is `429`
`rate-limit-exceeded` with `Retry-After`.

Some actions have their own, tighter limits that are part of the product:
sending with `incident` urgency is limited per sender (RFC 0006). Those
are also `429` `rate-limit-exceeded`, and the error's `detail` names the
limit that was hit.

### Size limits

| Limit | Value | Error |
| --- | --- | --- |
| Post body text | 40,000 characters | `422` `validation-failed` on `/data/attributes/body/text` |
| Request document | 1 MiB | `413` `payload-too-large` |
| Attachments on one post | 10 | `422` `validation-failed` |
| One uploaded file | 100 MiB | `413` `payload-too-large`, checked when the upload starts |

Files never pass through the API. `POST /v1/uploads` declares the file's
name, type and size and returns a short-lived upload URL, valid for 15
minutes, that the client sends the bytes to directly. The size is checked
against the limit before the URL is issued. Each RFC may set tighter
limits for its own resources.

### Request IDs

Every response carries an `X-Request-Id` header. A client may send its own
`X-Request-Id` (up to 128 characters), which the server echoes; otherwise
the server generates one. Every response document also carries it as
`meta.request_id`, so it survives being copied out of a log.

### Deprecation

An endpoint, parameter, attribute or relationship scheduled for removal is
marked on every response that uses it with:

- `Deprecation`, the date it was deprecated, per the IETF Deprecation
  header;
- `Sunset`, the date after which it may stop working, per RFC 8594;
- `Link` with `rel="deprecation"` pointing at the migration guide.

Removal itself only happens in a new major version.

### Errors

An error response is a JSON:API document with a top-level `errors` array
and no `data`. Each error object uses these members:

| Member | Always present | Meaning |
| --- | --- | --- |
| `status` | Yes | The HTTP status code, as a string |
| `code` | Yes | A stable, product-wide slug for the error, such as `validation-failed` or `approval-required` |
| `title` | Yes | A short, fixed summary of the error type |
| `detail` | Yes | What went wrong with this request, in a sentence a person can act on |
| `links.type` | Yes | A permanent URL identifying the error type, resolving to its documentation |
| `source` | Where it applies | `pointer` (a JSON Pointer into the request document), `parameter` (a query parameter) or `header` (a request header) |
| `meta` | Where documented | Whatever the client needs to recover, per error type |

`code` and `links.type` are the contract. Clients switch on `code`, or on
`links.type`, never on `title` or `detail`, which may be reworded. A code
or type is never renamed or removed, only added or deprecated. The
document's top-level `meta.request_id` identifies the request.

An error response's HTTP status is the most generally applicable status
of its errors: when every error shares a status, it is that status; when
they differ, it is the general class, for example `400` for a mix of
`4xx` errors.

Validation failures produce one error per invalid field, each pointing at
it:

```json
{
  "jsonapi": { "version": "1.1" },
  "errors": [
    {
      "status": "422",
      "code": "validation-failed",
      "title": "Validation Failed",
      "detail": "The due by date must be in the future.",
      "source": { "pointer": "/data/attributes/request/due_by" },
      "links": { "type": "https://apiguide.dev/errors/validation-failed" }
    },
    {
      "status": "422",
      "code": "validation-failed",
      "title": "Validation Failed",
      "detail": "The assignee must be a member of this space.",
      "source": { "pointer": "/data/relationships/assignee" },
      "links": { "type": "https://apiguide.dev/errors/validation-failed" }
    }
  ],
  "meta": { "request_id": "7c0e5a52-3a4f..." }
}
```

A resource the caller cannot see is `404`, not `403`. Answering `403`
would confirm that the resource exists, which leaks the existence of
private spaces, direct conversations and their threads. `403` is reserved
for a resource the caller can see but may not change in this way:
`insufficient-scope`, or the product-specific errors below.

#### Generic errors

Errors that are the same in every API take their `code` from the
[apiguide.dev errors catalogue](https://apiguide.dev/errors/), and their
`links.type` is the catalogue page, which explains the error, its causes
and how to fix it. Longhand does not keep its own copy of that
documentation.

| `code` | Status | When |
| --- | --- | --- |
| [`malformed-request-body`](https://apiguide.dev/errors/malformed-request-body/) | 400 | The body is not valid JSON, or not a valid JSON:API document |
| [`invalid-pagination-cursor`](https://apiguide.dev/errors/invalid-pagination-cursor/) | 400 | A cursor is unknown, expired or from another query |
| [`invalid-query-parameter`](https://apiguide.dev/errors/invalid-query-parameter/) | 400 | A query parameter is unknown, an `include` path or sort field is unsupported, or a filter value is invalid |
| [`unauthorized`](https://apiguide.dev/errors/unauthorized/) | 401 | No token, or an invalid one |
| [`expired-authentication-token`](https://apiguide.dev/errors/expired-authentication-token/) | 401 | The token, stream ticket or stream cookie has expired |
| [`insufficient-scope`](https://apiguide.dev/errors/insufficient-scope/) | 403 | The token lacks a required scope. `WWW-Authenticate` names the missing `scope`, per RFC 6750 |
| [`resource-not-found`](https://apiguide.dev/errors/resource-not-found/) | 404 | The resource does not exist, or the token cannot see it |
| [`method-not-allowed`](https://apiguide.dev/errors/method-not-allowed/) | 405 | The method is not supported on this path |
| [`not-acceptable`](https://apiguide.dev/errors/not-acceptable/) | 406 | `Accept` disqualifies every JSON:API media type |
| [`idempotency-key-conflict`](https://apiguide.dev/errors/idempotency-key-conflict/) | 409 | An `Idempotency-Key` reused with a different body, or while the first request is in flight |
| [`resource-conflict`](https://apiguide.dev/errors/resource-conflict/) | 409 | A uniqueness clash, such as a duplicate handle |
| [`precondition-failed`](https://apiguide.dev/errors/precondition-failed/) | 412 | `If-Match` did not match the current `ETag` |
| [`payload-too-large`](https://apiguide.dev/errors/payload-too-large/) | 413 | A request document or upload is over the limit |
| [`unsupported-media-type`](https://apiguide.dev/errors/unsupported-media-type/) | 415 | The body is not `application/vnd.api+json`, or names an unsupported extension |
| [`validation-failed`](https://apiguide.dev/errors/validation-failed/) | 422 | A field in the request document failed validation |
| [`precondition-required`](https://apiguide.dev/errors/precondition-required/) | 428 | A `PATCH` or `DELETE` on a mutable resource without `If-Match` |
| [`rate-limit-exceeded`](https://apiguide.dev/errors/rate-limit-exceeded/) | 429 | Too many requests, or too many `incident` sends. Includes `Retry-After` |
| [`internal-server-error`](https://apiguide.dev/errors/internal-server-error/) | 500 | An unexpected failure |
| [`service-unavailable`](https://apiguide.dev/errors/service-unavailable/) | 503 | Maintenance or overload. Includes `Retry-After` |

The spec's `unprocessable-query` (`422`) is not used: JSON:API requires
`400` for every query parameter problem, so those become
`invalid-query-parameter`.

#### Product-specific errors

Errors that only exist in Longhand have their documentation at
`https://api.longhand.example/problems/{code}` in documentation, which is
their `links.type`. Each page links to the closest apiguide.dev entry for
the general HTTP behaviour. Per ADR 0002, these URLs are not published
until Longhand has a real domain, because a type is promised to be
permanent.

| `code` | Status | When | `meta` | Closest apiguide.dev entry |
| --- | --- | --- | --- | --- |
| `approval-required` | 403 | An agent attempted an action its owner must approve. A draft was created instead | `draft`, `approver` | Insufficient Scope |
| `invalid-transition` | 409 | A state change not allowed from the current state, such as completing a declined request | `current_state`, `allowed`, `blocking` | Resource Conflict |
| `uncited-content` | 422 | A brief item has no citation, or cites something outside the brief's source bundle (RFC 0007). One error per item, with `source.pointer` | | Validation Failed |
| `decision-immutable` | 409 | An attempt to edit a published decision instead of superseding it | `decision` | Resource Conflict |
| `incident-only` | 422 | `incident` urgency used outside an incident thread | `thread_purpose` | Validation Failed |
| `last-owner` | 409 | Removing, demoting or deactivating a workspace's last owner (RFC 0003) | | Resource Conflict |
| `scope-exceeds-owner` | 422 | Giving an agent a scope or space beyond its owner's (RFC 0003) | `scopes`, `spaces` | Validation Failed |
| `agent-limit-reached` | 409 | Giving a member more agents than the workspace allows (RFC 0003) | `limit`, `owned` | Resource Conflict |
| `thread-not-open` | 409 | Posting, publishing or reacting in a resolved or archived thread, or an archived space (RFC 0004) | `status` | Resource Conflict |
| `unsupported-operations` | 400 | An atomic operations request that is not a documented composition (RFC 0004) | | Malformed Request Body |
| `not-a-respondent` | 403 | Responding to a check-in run the caller was not asked in (RFC 0008) | `run` | Insufficient Scope |

Later RFCs add product-specific errors as they need them, each with its
code, status, `meta` members and closest generic entry. This RFC is the
index: a new error code is added here in the same change that introduces
it.

A product-specific error carries what the client needs to recover in
`meta`, using resource identifier objects for anything it refers to:

```json
{
  "jsonapi": { "version": "1.1" },
  "errors": [
    {
      "status": "403",
      "code": "approval-required",
      "title": "Approval Required",
      "detail": "Triage needs approval from its owner to assign requests.",
      "links": { "type": "https://api.longhand.example/problems/approval-required" },
      "meta": {
        "draft": { "type": "requests", "id": "req_01JAA4..." },
        "approver": { "type": "members", "id": "mem_01JA7Q..." }
      }
    }
  ],
  "meta": { "request_id": "b41d09e8-6f2c..." }
}
```

### What this changes in RFC 0001

RFC 0001 lists RFC 9457 problem details among the standards Longhand
uses. JSON:API defines its own error format, and an API cannot return
both, so Longhand's errors are JSON:API error objects. The goal behind the
RFC 0001 line survives: every error has a permanent, documented type, now
carried in `links.type`. ADR 0005 records the change.

## Alternatives considered

- **The spec's own format: a `data` and `meta` envelope with flat
  resources and RFC 9457 errors.** Simpler to read, but bespoke: every
  client needs Longhand-specific code, and fetching related resources
  either takes a request per reference or a homemade expansion parameter.
- **JSON:API's `include` on top of the spec's format.** Borrows the one
  feature most wanted while keeping RFC 9457, but produces a format that
  is neither the spec's nor JSON:API, which no library supports.
- **RFC 9457 errors alongside JSON:API documents.** RFC 9457's `type` is
  the better-known idea, but JSON:API clients expect an `errors` array,
  and JSON:API 1.1's `links.type` carries the same permanent, documented
  type.
- **Offset pagination.** Simpler to explain, but it skips or repeats items
  when rows are added during paging, which is constant in a conversation
  product.
- **Header-based versioning.** Keeps URLs stable, but is invisible in logs,
  links and browsers, and every client has to remember to send it.
- **`If-Match` only where conflicts are likely.** Fewer `428`s, but a
  second rule to learn and a judgement call for every new resource.

## Decisions this records

- **The REST API is JSON:API 1.1,** with plural `snake_case` types and
  `snake_case` member names, and references as relationships.
- **Errors are JSON:API error objects,** typed through `links.type` by the
  apiguide.dev catalogue or Longhand's own documentation. This replaces
  RFC 9457 from RFC 0001.
- **The major version is in the URL path,** and the breaking-change list
  above defines when it changes.
- **Identifiers are prefixed ULIDs,** with the prefixes above, generated
  by the server.
- **Collections use the JSON:API cursor pagination profile,** with a
  stable order ending in the ULID.
- **Unsupported query parameters, includes and sorts are `400`**
  `invalid-query-parameter`.
- **State changes are `PATCH`es of the resource's state,** and actions that
  produce a new resource are creates. There are no action endpoints.
- **Every `POST` accepts an idempotency key,** kept for 24 hours and
  scoped to member, method and path.
- **`If-Match` is required** on every `PATCH` and `DELETE` of a mutable
  resource.
- **A resource the caller cannot see is `404`,** never `403`, including
  inside `included`.
- **Files go directly to storage** through short-lived upload URLs, never
  through the API, with the limits above.
- **Every response carries a request ID.**
- **Deprecation uses the `Deprecation` and `Sunset` headers,** and removal
  only happens in a new major version.

## Open questions

None. Resolved in review on 2026-10-08:

1. **Related resources** follow JSON:API: `include`, and the full JSON:API
   document format with it.
2. **`If-Match`** is required, with `428` `precondition-required`, a new
   apiguide.dev page.
3. **Size limits** are the ones above, set on advice.
4. **Sparse fieldsets** are not supported in v1; every response returns
   full resources.
5. **State changes** are `PATCH`es, and actions that produce a resource
   are creates.
6. **`invalid-query-parameter`** gets its own apiguide.dev page, since the
   catalogue's `unprocessable-query` is `422` and JSON:API requires `400`.
