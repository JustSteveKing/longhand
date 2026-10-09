# RFC 0005: Requests and decisions

- **Status:** Accepted
- **Created:** 2026-10-09
- **Depends on:** RFC 0003, RFC 0004, ADR 0013, ADR 0017, ADR 0022
- **Amended by:** RFC 0006

## Summary

Requests and decisions are the two things async teams lose most often in
chat. A request is an ask with one assignee, a due date and a state; a
decision is an immutable record of what was decided, by whom and why.
Both are created through a post, so they always have context, and both
live on as resources of their own. This RFC defines both, their
lifecycles, who may move them, the Atomic Operations compositions that
create them, and the decision log. It is section 7 of
[the spec](../spec.md).

## Problem

The spec's two resources are right. What needs settling:

- Creating either one means a post and a second resource in one request,
  and resolving a decision thread means a decision as well. ADR 0022
  allows that only in documented compositions, and this RFC documents
  them.
- The spec's seven action endpoints become `PATCH`es (ADR 0013).
- Reassigning is open to "assignee or requester", while RFC 0003 made
  `request.assign` need `requests:assign` for anyone other than the
  caller. The two need reconciling.
- A request's `history` is a list of member IDs inside an attribute, which
  JSON:API discourages, and a request carries an `urgency` that can
  disagree with its post's.
- An agent can draft a decision, but nothing says who publishes it or
  what that means for `decided_by`.
- Superseding a decision adds a post to the original thread, which RFC
  0004 forbids if that thread is closed.

## Goals

1. Every request has exactly one assignee, a due date, and a state that
   only the right person can change.
2. Every decision is a fact that cannot be edited, only superseded, and
   the chain of supersession can always be followed.
3. Every decision is published by a human who is named as deciding it.
4. Anyone reading an old discussion finds the decision that replaced
   its outcome.
5. A person can see, across every space, what they owe and what they are
   waiting on.

## Non-goals

- Requests with several assignees. Asking several people is several
  requests, which keeps ownership honest.
- Subtasks, dependencies between requests, and estimates.
- Retracting a decision without a replacement. "We will not do this after
  all" is a decision too.
- Deleting requests or decisions.

## Design

### Requests

```json
{
  "type": "requests",
  "id": "req_01JAA4...",
  "attributes": {
    "state": "pending",
    "due_by": "2026-10-10T12:00:00Z",
    "done_when": "Comments on the spec or a thumbs up in this thread",
    "proposed_due_by": null,
    "lands_at": "2026-10-09T08:30:00Z",
    "overdue": false,
    "created_at": "2026-10-08T11:42:00Z"
  },
  "relationships": {
    "post": { "data": { "type": "posts", "id": "pst_01JAA3..." } },
    "thread": { "data": { "type": "threads", "id": "thr_01JAA1..." } },
    "requester": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "assignee": { "data": { "type": "members", "id": "mem_01JA7R..." } },
    "transitions": { "links": { "related": "/v1/requests/req_01JAA4.../transitions" } }
  }
}
```

- **The requester** is the post's author. When an agent writes the
  request, it is the requester and its owner answers for it, as with
  anything it does.
- **The assignee** is one member who can see the thread: a person or an
  agent. Assigning work to an agent is one of the things Longhand is for.
  The requester may assign themselves, which makes a visible commitment
  ("I will have this done by Friday") rather than a private to-do.
- **`due_by`** must be in the future when it is set. `done_when` says what
  finished looks like, up to 500 characters, and is optional.
- **Urgency** belongs to the post. A request has no `urgency` of its own,
  so the two can never disagree; the spec's `request.urgency` is the
  post's `urgency`.
- **`lands_at`** is when the assignee will actually see it, worked out by
  RFC 0006, so the requester knows whether "tomorrow at noon" leaves the
  assignee any working time.
- **`overdue`** is `true` once `due_by` has passed while the request is
  `pending` or `accepted`. It is a fact about time, not a state.

Anyone who can see the thread can see its requests.

### Creating a request

A request is created with its post, through `POST /v1/operations`:

```json
{
  "atomic:operations": [
    {
      "op": "add",
      "data": {
        "type": "posts",
        "lid": "ask",
        "attributes": {
          "intent": "request",
          "body": { "format": "markdown", "text": "Can you review the webhook retry spec?" },
          "urgency": "today"
        },
        "relationships": {
          "thread": { "data": { "type": "threads", "id": "thr_01JAA1..." } }
        }
      }
    },
    {
      "op": "add",
      "data": {
        "type": "requests",
        "attributes": {
          "due_by": "2026-10-10T12:00:00Z",
          "done_when": "Comments on the spec or a thumbs up in this thread"
        },
        "relationships": {
          "post": { "data": { "type": "posts", "lid": "ask" } },
          "assignee": { "data": { "type": "members", "id": "mem_01JA7R..." } }
        }
      }
    }
  ]
}
```

The thread's first post can be a request too: the thread, the post and
the request are then three operations, in that order. A thread with
`purpose: "request"` must start that way.

There is no `POST /v1/requests`; it answers `405` and points at
`/v1/operations`. A post with the `request` intent and no request, or a
request with a post of any other intent, is `422` `validation-failed`.

**Drafts.** When the post is a draft, the request is created in state
`draft`, visible only to whoever can see the draft, and delivers nothing.
Publishing the post (RFC 0004) moves the request to `pending` in the same
change. Discarding the post deletes it.

### Request lifecycle

Every change is a `PATCH /v1/requests/{request}` with `If-Match`: of
`state` for a transition, or of the `assignee` relationship for a
reassignment. Each one is recorded as a `request_transitions` resource.

| From | To | Action | Needs | Who |
| --- | --- | --- | --- | --- |
| `draft` | `pending` | (publishing the post) | | Whoever publishes it |
| `pending` | `accepted` | `request.accept` | | Assignee |
| `pending` | `declined` | `request.decline` | `meta.note`, required | Assignee |
| `pending`, `accepted` | `done` | `request.complete` | `meta.note`, optional | Assignee |
| `pending`, `accepted` | `pending`, new assignee | `request.assign` | The new `assignee` | Assignee, requester, admin |
| `pending`, `accepted` | `cancelled` | `request.cancel` | `meta.note`, optional | Requester, admin |
| `declined`, `done` | `pending` | `request.reopen` | `meta.note`, optional | Requester |

- **Completing from `pending`** is allowed. The spec required accepting
  first, which is a step with no information in it when the assignee has
  simply done the thing.
- **`cancelled` is final.** Asking again is a new request.
- **Reassigning** always returns the request to `pending`, because the new
  assignee has not agreed to anything. It needs `requests:assign`, except
  in the two cases RFC 0003 allows with `requests:write` alone: taking a
  request on yourself, and an assignee handing it back to the requester.
  The new assignee must be able to see the thread.
- **Due dates and `done_when`** change with a plain `PATCH`, as action
  `request.update`, by the requester only. The assignee is told, through
  `request.updated` and RFC 0006. A change is refused once the request is
  `done`, `declined` or `cancelled`.
- **The assignee proposes a new date** rather than moving it, because a
  deadline is the requester's to give. They `PATCH` `proposed_due_by`,
  with an optional `meta.note`, as action `request.propose_due_by`, and
  the requester gets a `due_by_proposed` inbox item. The requester
  accepts by setting `due_by` to the proposed date, which clears the
  proposal, or declines by setting `proposed_due_by` to `null`, with an
  optional `meta.note`. The assignee can withdraw it the same way. There
  is one proposal at a time, and a new one replaces the old. Until it is
  accepted, `due_by` stands, and so does `overdue`. When the requester is
  also the assignee, there is nobody to ask, and they change `due_by`
  directly.
- **Notes** go in the request document's top-level `meta.note`, up to
  2,000 characters, and are stored on the transition, never on the
  request. Every action on a request, including reassignments and date
  proposals, is recorded as a transition, even when the state does not
  change.
- A transition from the wrong state is `409` `invalid-transition`, with
  `meta.current_state` and `meta.allowed`.

**Overdue.** When `due_by` passes while a request is `pending` or
`accepted`, `request.overdue` fires once, `overdue` becomes `true`, and
both people get a `request_overdue` inbox item. Moving `due_by` into the
future clears `overdue`, and a later deadline can fire it again.

**Losing access.** If the assignee can no longer see the thread (removed
from the space, or deactivated), the request keeps its state and
assignee, and the requester gets a `request_needs_reassignment` inbox
item. Nothing is reassigned automatically. RFC 0006 adds the inbox
reason.

### Request transitions

```json
{
  "type": "request_transitions",
  "id": "rtr_01JAA9...",
  "attributes": {
    "action": "request.decline",
    "from_state": "pending",
    "to_state": "declined",
    "note": "Out until Monday, Priya knows this area better",
    "created_at": "2026-10-08T14:02:00Z"
  },
  "relationships": {
    "request": { "data": { "type": "requests", "id": "req_01JAA4..." } },
    "by": { "data": { "type": "members", "id": "mem_01JA7R..." } },
    "assignee": { "data": { "type": "members", "id": "mem_01JA7R..." } }
  }
}
```

This replaces the spec's `history` attribute: the members are
relationships, so they can be included, and an invisible member is left
out per ADR 0012. Transitions are listed oldest first at
`GET /v1/requests/{request}/transitions`, and are never edited.
`assignee` is who held the request after the transition.

### Finding requests

`GET /v1/requests` lists requests across every thread the caller can
see. The spec's `GET /v1/me/requests?role=` becomes filters:

- `filter[assignee]=me&filter[state]=pending,accepted` is what a person
  owes.
- `filter[requester]=me&filter[state]=pending,accepted` is what they are
  waiting on.
- Also `filter[thread]`, `filter[space]`, `filter[overdue]` and
  `filter[due_before]`.
- Sorted by `due_by` by default, soonest first; also by `created_at`.
- `include=post,thread,requester,assignee`.

A delegated agent's `me` is its principal (RFC 0003), so an assistant
lists the person's requests.

### Decisions

```json
{
  "type": "decisions",
  "id": "dec_01JAB0...",
  "attributes": {
    "status": "active",
    "summary": "Use SQS for the billing queue",
    "rationale": "Redis persistence risk is not worth it for billing.",
    "tags": ["infrastructure", "billing"],
    "decided_at": "2026-10-08T15:00:00Z",
    "created_at": "2026-10-08T15:00:00Z"
  },
  "relationships": {
    "post": { "data": { "type": "posts", "id": "pst_01JAAZ..." } },
    "thread": { "data": { "type": "threads", "id": "thr_01JA9X..." } },
    "decided_by": { "data": [{ "type": "members", "id": "mem_01JA7Q..." }] },
    "published_by": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "supersedes": { "data": null },
    "superseded_by": { "data": null },
    "chain": { "links": { "related": "/v1/decisions/dec_01JAB0.../chain" } }
  }
}
```

- **`summary`** is one line, up to 200 characters. **`rationale`** is
  Markdown, up to 10,000 characters, and optional, though a decision
  without one is a poor record.
- **`decided_by`** is one or more humans who can see the thread. It never
  includes an agent.
- **`status`** is `draft`, `active` or `superseded`.
- **`tags`** are lowercase words or hyphenated phrases, up to 10 per
  decision. They are the one thing about a decision that can change.
- **`decided_at`** is when it was published.

The spec's `informed` list of spaces is dropped. A decision is visible
only to people who can see its thread, so informing a space whose members
cannot see the thread would either tell them nothing or leak a private
conversation. People who need to know are participants, or the decision
is recorded somewhere they can see it.

Anyone who can see the thread can see its decisions. A decision is never
deleted, and a post that created one cannot be deleted (RFC 0004).

### Recording a decision

Every decision comes from a post with the `decision` intent, so it
always has a place in a conversation. There are three ways to record
one, each a composition on `/v1/operations`:

1. **A decision post:** add a post with `intent: "decision"`, then add a
   decision whose `post` is that post's `lid`.
2. **Resolving a decision thread in one step:** the decision post, its
   decision, then an `update` of the thread to `status: "resolved"` with
   its `outcome`, carrying the thread's ETag in the operation's
   `meta.if_match` (ADR 0035). The thread's open requests still block it
   (RFC 0004).
3. **Superseding a decision:** a decision post and its decision, with
   `supersedes` pointing at the decision it replaces (below).

A `decision` thread can only be resolved while it has an active decision,
recorded in the same request or earlier. Resolving it without one is
`409` `invalid-transition` with `meta.blocking` saying so. The thread's
`decision` relationship (RFC 0004) is its latest active decision.

Other threads can record decisions too, and doing so does not resolve
them.

### Decisions stay human

An agent with `decisions:write` creates decisions as drafts, never as
`active`, whatever its other scopes. It can propose `decided_by`.

- A draft decision is visible to every human who can see the thread, as
  with an agent's draft post, and is listed in the decision log only for
  them, under `filter[status]=draft`.
- Any human who can see the thread and has `decisions:write` publishes
  it, as action `decision.publish`, with a `PATCH` of `status` to
  `active`. The same `PATCH` may correct the summary, rationale,
  `decided_by` and tags, since nothing about a draft is fixed yet.
- The publisher is added to `decided_by` if they are not already in it,
  because publishing a decision is vouching for it. `published_by`
  records them.
- Publishing the decision publishes its post, and the reverse: they are
  one thing.
- An agent attempting to set `status` to `active` is `403`
  `insufficient-scope` with `meta.action` `decision.publish`, which no
  scope can grant an agent.

A person can also create a decision as a draft, to publish later.

### Immutability

Once `active`, a decision's summary, rationale, `decided_by`, post and
thread never change. A `PATCH` that touches anything except `tags` is
`409` `decision-immutable`. Tags change as action `decision.tag`, by
anyone who can see the thread and has `decisions:write`, because tagging
organises the log without changing what was decided.

### Superseding

Reversing or replacing a decision means recording a new one with
`supersedes` set to it.

- Only members in the original's `decided_by`, or admins, may supersede
  it, as action `decision.supersede`.
- Only an `active` decision can be superseded. Superseding one that is
  already superseded is `409` `invalid-transition`, with its
  `superseded_by` in `meta`, so the chain can never fork.
- The new decision can be in any thread, so a later conversation can
  overturn an earlier one, but the person superseding must be able to
  see both.
- When the new decision is published, the original's `status` becomes
  `superseded` and its `superseded_by` is set, in the same change.
- Longhand adds an `fyi` post to the original thread, authored by the
  publisher, linking to the new decision, so anyone reading the old
  discussion finds the new answer. This is the one post allowed in a
  resolved or archived thread; it does not reopen it, deliver to anyone,
  or count as activity for staleness.

### The decision log

`GET /v1/decisions` lists decisions across every thread the caller can
see, newest first. The log is the main reason teams will keep using
this over chat, so it is a collection of its own.

- Filters: `filter[space]`, `filter[thread]`, `filter[tag]`,
  `filter[status]` (default `active`), `filter[decided_by]` (accepting
  `me`), `filter[decided_after]` and `filter[decided_before]`.
- `include=post,thread,decided_by,supersedes,superseded_by`.
- `GET /v1/decisions/{decision}/chain` returns the whole supersession
  chain the decision belongs to, oldest first, whichever link in it was
  asked for. A decision in the chain that the caller cannot see is left
  out, and the chain closes over the gap.

### Actions

| Action | Scope | Who |
| --- | --- | --- |
| `request.create` | `requests:write` | Any member of the space |
| `request.accept`, `request.decline`, `request.complete` | `requests:write` | Assignee |
| `request.assign` | `requests:assign`, or `requests:write` to take it on or hand it back | Assignee, requester, admin |
| `request.update` | `requests:write` | Requester |
| `request.propose_due_by` | `requests:write` | Assignee to propose or withdraw; requester to decline |
| `request.cancel` | `requests:write` | Requester, admin |
| `request.reopen` | `requests:write` | Requester |
| `decision.create` | `decisions:write` | Any member of the space; agents as drafts only |
| `decision.publish` | `decisions:write` | Any human who can see the thread |
| `decision.tag` | `decisions:write` | Anyone who can see the thread |
| `decision.supersede` | `decisions:write` | The original's `decided_by`, admin |

Reading requests, transitions and decisions needs `threads:read`.

### Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/v1/operations` | Create a request or decision with its post, or resolve a decision thread with its decision |
| `GET` | `/v1/requests` | Requests across visible threads |
| `GET` / `PATCH` | `/v1/requests/{request}` | Read, change state, reassign, update |
| `GET` | `/v1/requests/{request}/transitions` | A request's history |
| `GET` | `/v1/decisions` | The decision log |
| `GET` / `PATCH` | `/v1/decisions/{decision}` | Read, publish a draft, change tags |
| `GET` | `/v1/decisions/{decision}/chain` | The supersession chain |

### Errors and identifiers

No new error codes. This RFC uses `invalid-transition` and
`decision-immutable` from RFC 0002, and `insufficient-scope` with
`meta.action`.

One new prefix, added to RFC 0002's table: `rtr_` for
`request_transitions`.

### Events

From the spec's catalogue: `request.created` (on publishing),
`request.accepted`, `request.declined`, `request.reassigned`,
`request.completed`, `request.cancelled`, `request.reopened`,
`request.overdue`, `decision.recorded`, `decision.drafted` and
`decision.superseded`. Plus:

| Event | Fires when |
| --- | --- |
| `request.updated` | `due_by` or `done_when` changes |
| `request.due_by_proposed` | The assignee proposes a new due date |
| `request.due_by_proposal_closed` | A proposal is accepted, declined or withdrawn, with `outcome` saying which |
| `decision.tagged` | A decision's tags change |

`decision.drafted` fires for any draft decision, not only an agent's.

## Alternatives considered

- **A `history` attribute on the request.** The spec's shape, with member
  IDs inside an attribute where they cannot be included or hidden.
- **Requiring acceptance before completion.** The spec's rule, and a
  ceremony with no information in it.
- **Server-generated decision posts when resolving.** One operation
  fewer, and a post the author never wrote. The composition keeps every
  post the work of the person it is attributed to.
- **The assignee moves the deadline and the requester is told.** Less
  friction, and it lets the person who owes the work decide when it is
  owed.
- **Keeping `informed`.** See "Decisions"; it can only tell people
  nothing, or tell them too much.
- **Letting `cancelled` reopen.** Little gained over asking again, and
  one more transition to reason about.
- **Agents publish decisions their owner pre-approves.** RFC 0003 already
  rules it out: decisions stay human.

## Decisions this records

- **Requests and decisions are created with their posts through Atomic
  Operations,** in the compositions above, and have no create endpoint of
  their own.
- **A request's urgency is its post's.**
- **A request's history is a `request_transitions` collection,** with
  notes passed in `meta.note`.
- **A request can be completed without being accepted,** and a cancelled
  request stays cancelled.
- **Reassigning needs `requests:assign`,** except to take a request on or
  hand it back to the requester.
- **Requests can be assigned to agents and to the requester,** whose
  self-assigned request is a visible commitment.
- **Only the requester sets a due date;** the assignee proposes one.
- **Decisions have no `informed` list.**
- **A decision thread resolves only with an active decision.**
- **Whoever publishes a decision is one of its `decided_by`,** and agents
  never publish one.
- **Only an active decision can be superseded,** so the chain never
  forks, and the original thread gets a linking post even when closed.

## Open questions

None. Resolved in review on 2026-10-09:

1. **`informed`** is dropped.
2. **Self-assigned requests** are allowed.
3. **Deadlines** are the requester's; the assignee proposes a new date for
   the requester to accept or decline.
