# RFC 0004: Spaces, threads and posts

- **Status:** Accepted
- **Created:** 2026-10-09
- **Depends on:** RFC 0002, RFC 0003, ADR 0011, ADR 0012, ADR 0013
- **Amended by:** RFC 0005, RFC 0006, RFC 0007, RFC 0011, API contract
  review, 2026-10-09

## Summary

A space holds threads and a thread holds posts. There is no way to post
into a space without a thread, and that is the single biggest difference
from channel-based chat. This RFC defines the three resources, how a
space's membership works, the thread lifecycle from first post to
archive, what each post intent does, drafts, editing and deletion,
mentions, reactions and attachments. It is sections 5 and 6 of
[the spec](../spec.md). Requests and decisions, which posts create, are
RFC 0005; who is told about a post and when is RFC 0006.

## Problem

The spec's model is right, and it was written before JSON:API, ADR 0013
and RFC 0003. Several things in it now need a different shape or an
answer:

- Creating a thread needs a title and a first post in one request, and
  JSON:API creates one resource per request.
- Six action endpoints (`/resolve`, `/reopen`, `/archive`, `/publish`,
  `/cursor`, `/reactions/{emoji}`) have to become creates, updates and
  deletes.
- `waiting_on` has no defined shape, and a post's `author` embeds a
  `type`, which JSON:API forbids (ADR 0019).
- There is no way to list a space's members, and no rule for who may
  change a space's settings, resolve a thread, or publish an agent's
  draft.
- An agent with `posts:write:draft` can create a thread, whose first
  post is then a draft that nobody else can see.

## Goals

1. Every conversation has a title, a purpose, one human owner and an
   end, and resolving it records what happened.
2. A space's membership decides who sees what is in it, and nothing (a
   mention, an include, an error) reveals its contents to anyone outside
   it.
3. A post's intent decides how the rest of Longhand treats it, and
   cannot be changed once that would leave a request or decision behind.
4. Nothing an agent writes reaches anyone until a human has published it,
   unless the agent's scopes say otherwise.
5. Deleting a post never breaks what cites it.

## Non-goals

- Nested replies. Posts are flat; a sub-conversation is a new, linked
  thread.
- Moving a thread to another space.
- Group mentions such as `@here` or `@space`.
- Read receipts, unread counts and "seen by".
- Deleting spaces, which belongs to the retention RFC.
- Custom emoji, file previews and thumbnails.

## Design

### Spaces

```json
{
  "type": "spaces",
  "id": "spc_01JA9S...",
  "attributes": {
    "kind": "team",
    "name": "Billing",
    "description": "Billing service and invoicing",
    "visibility": "workspace",
    "status": "active",
    "default_urgency": "today",
    "max_urgency": "now",
    "stale_after": "P7D",
    "ends_at": null,
    "member_count": 6,
    "created_at": "2026-02-03T10:00:00Z"
  },
  "relationships": {
    "owner": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "memberships": { "links": { "related": "/v1/spaces/spc_01JA9S.../memberships" } },
    "threads": { "links": { "related": "/v1/spaces/spc_01JA9S.../threads" } }
  }
}
```

`kind` is one of:

- `team`, for an ongoing group or function.
- `project`, for time-boxed work. A project space may have an `ends_at`,
  and archives itself, with `reason: "project_ended"`, once that has
  passed and every thread in it is resolved or archived.
- `direct`, for a private conversation between two and eight members.

`visibility` is `workspace`, which any member except a guest can see and
join, or `private`, which only its members can see. Direct spaces are
always private.

**Admins know a private space exists, never what is in it.** To an admin
or owner who is not a member, a private team or project space appears in
`/v1/spaces` with its name, kind, owner, member count and members, and
`meta.access` set to `"limited"`. Its description is left out, and its
threads, posts and everything under them are `404`, per ADR 0012. They
can transfer it, archive it and remove members, so a space is never
stranded when its owner leaves, but they cannot join it or add anyone,
including themselves, because that would be access by another route.
Direct spaces are not shown to admins at all: who talks privately to whom
is itself content. To everyone else, a private space they are not in is
`404`.

`stale_after` is an ISO 8601 duration, at least `P1D`, or `null` to turn
staleness off. It defaults to `P7D`. `default_urgency` and `max_urgency`
are urgency tiers from RFC 0006; `incident` is never a space's default.

**Owners.** Every team and project space has one human owner, the
member who created it unless they name another. The owner and admins
change the space's settings, transfer it, archive it and unarchive it.
The new owner must be a human member of the space. If the owner is
deactivated, the space is flagged to admins, as RFC 0003 does for
threads.

**Direct spaces** have no name, no owner and no settings of their own;
they use the workspace defaults. Their membership is fixed: the set of
members is what identifies the space. A direct space is created with the
set in its `members` relationship, the caller included. Creating a
direct space for a set that already has one returns that space with `200 OK` and its
`Location`, instead of `201`, so a client never has to search first. To
add someone, start a new direct space with the larger set. Members
cannot leave a direct space, because that would change which space it
is; they mute it instead (below). Direct messages still use
titled threads; otherwise they become a second, untracked stream and the
whole model leaks.

**Archiving** is a `PATCH` of `status` to `archived`, and back to
`active`. An archived space is read-only: no new threads, posts,
reactions or members. Its threads keep their own status.

Names of team and project spaces are unique in a workspace, ignoring
case. A clash is `409` `resource-conflict`.

### Space memberships

Membership is a resource of its own, `space_memberships`, so a space's
members can be listed and paginated, and so joining and leaving are
creates and deletes:

```json
{
  "type": "space_memberships",
  "id": "smb_01JAB1...",
  "attributes": { "muted": false, "created_at": "2026-10-01T09:00:00Z" },
  "relationships": {
    "space": { "data": { "type": "spaces", "id": "spc_01JA9S..." } },
    "member": { "data": { "type": "members", "id": "mem_01JA7R..." } },
    "added_by": { "data": { "type": "members", "id": "mem_01JA7Q..." } }
  }
}
```

- Any non-guest member joins a `workspace` space by creating a membership
  for themselves.
- Any member of a space, other than a guest, adds another member to it.
  A guest is only ever added by someone else.
- A member leaves by deleting their own membership. The owner and admins
  remove others. The owner cannot leave until they transfer the space.
- An agent can only be added to a space its owner can see (RFC 0003).
  Adding an agent to a space does not add the space to its allow-list;
  only a human changes that.
- **Muting.** A member mutes a space they belong to, any kind, with a
  `PATCH` of their own membership's `muted`, as action `space.mute`.
  A muted space delivers nothing to them: no posts, questions or
  mentions reach their inbox, and it drops out of their default space
  list. Requests assigned to them and `incident` posts still arrive,
  because those are commitments rather than chatter. RFC 0006 applies
  the rule. Nobody else can see that a member has muted a space: `muted`
  is `null` on anyone else's membership.
- `muted` is the only attribute a membership has, so memberships carry
  an `ETag` and `PATCH` and `DELETE` take `If-Match`, like any other
  mutable resource (ADR 0011).

Removing someone from a space removes them as a participant of its
threads and closes their inbox items there. What they wrote stays.

### Threads

```json
{
  "type": "threads",
  "id": "thr_01JA9X...",
  "attributes": {
    "title": "Pick a queue driver for the billing service",
    "purpose": "decision",
    "status": "open",
    "decide_by": "2026-10-15T17:00:00Z",
    "waiting_until": null,
    "waiting_reason": null,
    "outcome": null,
    "proposed_outcome": null,
    "counts": { "posts": 12, "open_requests": 1 },
    "last_activity_at": "2026-10-08T11:40:00Z",
    "created_at": "2026-10-06T09:12:00Z"
  },
  "relationships": {
    "space": { "data": { "type": "spaces", "id": "spc_01JA9S..." } },
    "owner": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "participants": { "data": [{ "type": "members", "id": "mem_01JA7R..." }] },
    "waiting_on": { "data": null },
    "proposed_by": { "data": null },
    "related_threads": { "data": [] },
    "decision": { "data": null },
    "summary": { "data": null },
    "last_seen_post": { "data": { "type": "posts", "id": "pst_01JAA2..." } },
    "posts": { "links": { "related": "/v1/threads/thr_01JA9X.../posts" } }
  }
}
```

**Purpose** is `discussion`, `decision`, `request`, `announcement` or
`incident`, set when the thread is created and fixed after that.

| Purpose | Rules |
| --- | --- |
| `discussion` | None beyond the defaults |
| `decision` | Resolving it requires a decision (RFC 0005). May have a `decide_by` |
| `request` | Its first post has the `request` intent |
| `announcement` | Only the owner posts without a `reply_to`; everyone else can only reply |
| `incident` | Posts may use the `incident` urgency tier (RFC 0006) |

`decide_by` is only accepted on `decision` threads; once it passes with
no decision, `thread.decide_by_passed` fires.

**The owner** is one human member of the space, who is nudged when the
thread goes stale and who decides when it is done. The creator owns it
unless they name someone else. When an agent creates a thread, the owner
is the human the agent acts for, or else the agent's owner, because an
agent cannot be accountable for a conversation.

**Participants** are the members actively involved: the owner, anyone
named when the thread is created or added later, and anyone who
publishes a post in it, who is added automatically. Every participant
must be a member of the space. Participants are who RFC 0006 routes a
thread's updates to; other members of the space can read the thread but
are not told about it.

**Waiting.** A thread moves to `waiting` when it is blocked on a member,
a date, or both. `waiting_on` is that member, `waiting_until` is that
date, and `waiting_reason` is a sentence saying what is needed. At least
one of `waiting_on` and `waiting_until` is required.

**Related threads** link a thread to others, in any space of the
workspace. The link is symmetric: linking A to B shows on both. A
related thread the caller cannot see is left out of the linkage, per ADR
0012.

**Proposed outcome** is set by an agent proposing a resolution, with
`proposed_by`, for the owner to accept or clear (RFC 0011).

**Summary** is the thread's latest roll-up, a brief anyone who can see
the thread can ask for (RFC 0007).

**Counts** never include drafts, and `open_requests` counts requests that
are pending or accepted.

### Creating a thread

A thread is always created with its first post, so a thread can never be
empty. JSON:API creates one resource per request, so Longhand supports
the [Atomic Operations extension](https://jsonapi.org/ext/atomic/) at
`POST /v1/operations`, which carries several operations and applies all
of them or none:

```http
POST /v1/operations
Content-Type: application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"
Accept: application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"
Idempotency-Key: 4f1c...

{
  "atomic:operations": [
    {
      "op": "add",
      "data": {
        "type": "threads",
        "lid": "thread",
        "attributes": {
          "title": "Pick a queue driver for the billing service",
          "purpose": "decision",
          "decide_by": "2026-10-15T17:00:00Z"
        },
        "relationships": {
          "space": { "data": { "type": "spaces", "id": "spc_01JA9S..." } },
          "participants": { "data": [{ "type": "members", "id": "mem_01JA7R..." }] }
        }
      }
    },
    {
      "op": "add",
      "data": {
        "type": "posts",
        "attributes": {
          "intent": "question",
          "body": { "format": "markdown", "text": "Redis or SQS? Constraints are in the ADR." }
        },
        "relationships": {
          "thread": { "data": { "type": "threads", "lid": "thread" } }
        }
      }
    }
  ]
}
```

The response is `200 OK` with `atomic:results`, one result per
operation in the same order: the thread, then the post, whose result
`meta` carries the post's `delivery` summary (below). An error in any
operation undoes them all, and its `source.pointer` names the operation,
such as `/atomic:operations/1/data/attributes/body/text`.

The rules for `/v1/operations`:

- **Only documented compositions are accepted.** This RFC defines one: a
  thread and its first post. RFC 0005 adds the ones for requests and
  decisions. Any other
  combination is `400` `unsupported-operations`.
- Each operation runs through the same Action, permission check and
  approval rules as it would alone. The `Idempotency-Key` covers the
  whole request.
- There is no `POST /v1/threads`. It answers `405` with `Allow: GET`,
  and its error `detail` points at `/v1/operations`.

**Draft threads.** When the first post is a draft, because an agent only
has `posts:write:draft`, the thread is a draft too: it has `status`
`draft`, only the people who can see the draft can see the thread, and
no `thread.created` fires. Publishing the first post opens the thread.
Discarding it deletes the thread.

### Thread lifecycle

Every change of status is a `PATCH /v1/threads/{thread}` of `status`
(ADR 0013), with `If-Match`.

| From | To | Action | Needs | Who |
| --- | --- | --- | --- | --- |
| `draft` | `open` | (publishing the first post) | | Whoever publishes it |
| `open` | `waiting` | `thread.wait` | `waiting_on` or `waiting_until`; `waiting_reason` | Owner, any participant, admin |
| `waiting` | `open` | `thread.resume` | | Owner, any participant, admin |
| `open`, `waiting` | `resolved` | `thread.resolve` | `outcome`; for a `decision` thread, a decision (RFC 0005) | Owner, admin |
| `resolved` | `open` | `thread.reopen` | Optional `meta.note` | Any member of the space |
| `open`, `resolved` | `archived` | `thread.archive` | | Owner, admin |
| `archived` | `open` | `thread.reopen` | Optional `meta.note` | Owner, admin |

- Resolving is refused while the thread has open requests: `409`
  `invalid-transition` with the requests in `meta.blocking`. They are
  completed, declined, cancelled or reassigned first (RFC 0005).
- A `waiting` thread whose `waiting_until` passes moves back to `open`
  by itself, with `surface: "system"` in the audit log.
- The reason for a reopen is not stored on the thread. It goes in the
  request document's top-level `meta.note`, and from there into
  `thread.reopened` and the audit log.
- Changing the title, owner, participants, related threads or
  `decide_by` is a plain `PATCH`, as action `thread.update`, by the owner,
  a participant or an admin. Transferring ownership is `thread.transfer`.

**Closed threads.** Nothing can be posted in a `resolved` or `archived`
thread, or in any thread of an archived space: `409` `thread-not-open`.
Reopening comes first, which is the point. A conversation that has ended
should not pick up a stray reply that nobody is responsible for.

### Staleness

An `open` thread with no published post, status change or new
participant for its space's `stale_after` emits `thread.stale` and adds a
`thread_stale` inbox item for the owner. Nothing happens to the thread
itself: the owner, or an agent subscribed to the event, decides whether
to nudge, resolve or archive it. It fires once per idle period. `waiting`
threads are never stale, and `waiting_until` returning a thread to
`open` starts a new idle period.

### Read position

Posts have no read or unread state. Each member has one read position
per thread, the last post they have seen, which briefs use for "since
you last looked" (RFC 0007). It appears on the thread as the
`last_seen_post` relationship, which is different for every caller.

A client moves it with
`PATCH /v1/threads/{thread}/relationships/last_seen_post`. It only ever
moves forward: a post older than the current position is ignored with
`200 OK`. Because of that, a stale write can never undo a newer one, and
the request takes no `If-Match`; requiring the thread's ETag would fail
every time anyone posted. The new position must be a published post in
the thread.

### Posts

```json
{
  "type": "posts",
  "id": "pst_01JAA2...",
  "attributes": {
    "intent": "question",
    "status": "published",
    "body": { "format": "markdown", "text": "Redis or SQS? Constraints are in the ADR. @priya" },
    "urgency": "today",
    "links": [{ "url": "https://github.com/...", "title": "ADR 014" }],
    "reactions": [{ "emoji": "👀", "count": 2, "me": false }],
    "answered": false,
    "answered_at": null,
    "edited_at": null,
    "published_at": "2026-10-08T11:40:00Z",
    "created_at": "2026-10-08T11:40:00Z"
  },
  "relationships": {
    "thread": { "data": { "type": "threads", "id": "thr_01JA9X..." } },
    "author": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "on_behalf_of": { "data": null },
    "published_by": { "data": null },
    "reply_to": { "data": null },
    "mentions": { "data": [{ "type": "members", "id": "mem_01JA7R..." }] },
    "files": { "data": [] },
    "answer": { "data": null },
    "request": { "data": null },
    "decision": { "data": null },
    "revisions": { "links": { "related": "/v1/posts/pst_01JAA2.../revisions" } }
  }
}
```

The spec's embedded `author` object becomes a relationship to the
member, whose `kind` says whether it is an agent (ADR 0019), with
`on_behalf_of` beside it. The spec's `attachments` split in two: `links`,
which are plain data, and `files`, a relationship to `uploads`.

A post is created with `POST /v1/posts`, with its `thread` relationship,
except a thread's first post, which comes with the thread. Creating a post
is an append, so it takes no `If-Match`. `reply_to` must be a published
post in the same thread. `status` is `published` unless the author sends
`draft`, or can only write drafts.

**Body.** `format` is `markdown` in v1: CommonMark with tables, task
lists, strikethrough and autolinks. Longhand stores the text as written.
Raw HTML in it is never rendered by first-party clients. The 40,000
character limit is RFC 0002's.

**Delivery summary.** The response to creating or publishing a post
carries, in its document `meta` (or the operation's result `meta`), a
`delivery` array saying when each recipient will see it, so the sender
knows what will happen:

```json
"meta": {
  "delivery": [
    { "member": "mem_01JA7R...", "tier": "today", "deliver_at": "2026-10-09T08:30:00Z", "reason": "outside_working_hours" }
  ]
}
```

Who the recipients are and how `deliver_at` is worked out is RFC 0006.
`urgency` defaults to the space's `default_urgency` and is capped at its
`max_urgency`; the post's `urgency` attribute shows the tier actually
used. `incident` outside an `incident` thread is `422` `incident-only`.

### Intents

| Intent | Use for | Effect |
| --- | --- | --- |
| `fyi` | Context nobody needs to act on | Never interrupts anyone. A mention in it reaches the member in their digest (RFC 0006). Appears in briefs when relevant to the reader |
| `question` | Something that needs an answer | Inbox item for mentioned members. Unanswered until the asker marks it answered |
| `request` | An ask with an owner and a deadline | Created with a request (RFC 0005), through `/v1/operations` |
| `update` | Progress on the thread's work | Feeds briefs and check-ins. Inbox item for the thread owner only |
| `decision` | Announcing a decision | Created with a decision (RFC 0005), through `/v1/operations` |

Intent defaults to `fyi`. Clients offer it as a lightweight choice, and
an agent can suggest a different intent on a draft for its author to
accept.

**Answering a question.** Only the asker marks a question answered, with
a `PATCH` of `answered` to `true`, as action `question.mark_answered`.
They can point the `answer` relationship at the post that answered it,
or leave it empty when the answer came from somewhere else. Marking it
sets `answered_at`, closes the related inbox items and fires
`question.answered`. Setting `answered` back to `false` clears the answer
and fires `question.reopened`. A reply never answers a question by
itself: replies are often clarifying questions, and only the asker knows
which one settled it. When the asker is an agent, the agent marks it,
like anything else it writes.

**Reminding the asker.** Marking is easy to forget, so Longhand reminds
the asker when a question looks answered and is not marked. Once a
question has a published reply from someone else, and is still
unanswered a working day later in the asker's hours, the asker gets a
`question_unmarked` inbox item at `today` urgency, naming the replies.
The reminder repeats weekly, at most three times in all, and then stops;
dismissing it does not stop the next one, marking the question does.
Reminders stop for good when the thread is resolved or archived, or the
post stops being a question. A question nobody has replied to never
reminds its asker, because the next move is not theirs. RFC 0006 adds
the inbox reason.

### Mentions

Longhand reads mentions from the body: `@handle`, outside code, naming a
member of the workspace. The `mentions` relationship is set by the
server and cannot be written. There is one source of truth, and an agent
writing Markdown cannot forget to send a list.

A mention never grants access. Mentioning a member who cannot see the
thread does not add them to the space or the thread, and they are not
told: their entry in the delivery summary has `reason: "no_access"`, so
the sender can add them deliberately. Mentioning a member who can see
the thread makes them a participant.

Editing a post delivers its new mentions only. Removing a mention does
not take back a delivery that has already happened.

### Drafts

A draft is a post with `status: "draft"`. Drafts never count, never
deliver and never appear in briefs.

- **A person's draft** is visible only to them.
- **An agent's draft** is visible to every human who can see the thread,
  marked as a draft, because it is waiting for one of them. Any of them
  with `posts:write` can publish it or discard it. The post keeps the
  agent as `author` and records who published it as `published_by`.
- **Publishing** is a `PATCH` of `status` to `published`, as action
  `post.publish`, and may change the body and intent in the same request.
  The thread must be open.
- **Discarding** is a `DELETE`, which removes a draft entirely, with
  `post.draft_discarded`.

A draft's intent can change freely. A draft request or decision is
published with its request or decision, as RFC 0005 describes.

### Editing and deletion

Only the author edits a published post, as action `post.edit`, with a
`PATCH` of its `body`, `links` or `files`.

- Edits in the first 15 minutes after publishing are corrections: no
  revision is kept and `edited_at` stays `null`.
- Later edits keep the previous version as a `post_revisions` resource,
  listed at `GET /v1/posts/{post}/revisions`, and set `edited_at`, which
  readers see as an edited marker. A revision holds the `body` and
  `links` as they were, `created_at` (when that version was replaced),
  and a `post` relationship.
- After publishing, intent can change between `fyi`, `question` and
  `update` only. Changing to or from `request` or `decision` is `409`
  `invalid-transition`, because those created other resources. Changing
  away from `question` clears the answer.

`DELETE /v1/posts/{post}`, by the author, the owner of the agent that
wrote it, or an admin, replaces the post with a tombstone: `status`
becomes `deleted`, the body, links, files and reactions go, and the ID,
author, thread and timestamps stay, so replies, citations in briefs and
decisions still resolve. What goes is `null` or empty rather than
absent, since every attribute is always present (RFC 0002). A deleted
post is `200` with the tombstone, never `404`. A post that created a request or decision cannot be deleted
(`409` `invalid-transition`); cancel the request or supersede the
decision instead.

### Reactions

A reaction is a resource of its own, `reactions`, with an `emoji` (a
single Unicode emoji), a `post` and a `member`, created with
`POST /v1/reactions` and removed with `DELETE /v1/reactions/{reaction}`.
It is never edited, so it has no `ETag` and its `DELETE` takes no
`If-Match`. A member
reacts with a given emoji once per post; a second is `409`
`resource-conflict`. Reactions need `posts:write`, are only allowed on
published posts in open threads, and never create inbox items or events.
The post's `reactions` attribute summarises them for the caller.

### Attachments

A post has at most 10 attachments, `links` and `files` together (RFC
0002).

- **Links** are a URL and an optional title.
- **Files** are uploaded first. `POST /v1/uploads` declares the name,
  content type and size, and returns an `uploads` resource with a
  15-minute `upload_url` that the client sends the bytes to directly
  (ADR 0014). The post then names the upload in `files`.
- An upload's attributes are `name`, `content_type`, `size` (at most
  100 MiB), `upload_url` and `created_at`; `upload_url` may be `null`
  on later reads. Its `post` relationship names the post it is attached
  to, once it is.
- An upload belongs to the member who created it and can be attached to
  one post. Uploads not attached within 24 hours are removed.
- Once attached, an upload is visible to whoever can see the post. Its
  `links.download` is a signed URL valid for 5 minutes, issued fresh on
  every read, and files are always served as downloads, never inline.
  `links.download` may be `null` when there is nothing to download.

### Actions

The actions this RFC adds, for scope checks, approval rules and the audit
log (ADR 0017):

| Action | Scope | Who |
| --- | --- | --- |
| `space.create` | `spaces:write` | Any non-guest member |
| `space.update`, `space.transfer`, `space.archive`, `space.unarchive` | `spaces:write` | Owner, admin |
| `space.join` | `spaces:write` | Any non-guest member, for a `workspace` space |
| `space.add_member` | `spaces:write` | Any non-guest member of the space |
| `space.remove_member` | `spaces:write` | The member themselves (not from a direct space), the owner, an admin |
| `space.mute` | `spaces:write` | The member themselves |
| `thread.create` | `threads:write` | Any member of the space |
| `thread.update`, `thread.wait`, `thread.resume` | `threads:write` | Owner, participant, admin |
| `thread.resolve`, `thread.archive`, `thread.transfer` | `threads:write` | Owner, admin |
| `thread.reopen` | `threads:write` | See the lifecycle table |
| `post.create` | `posts:write`, or `posts:write:draft` for drafts | Any member of the space |
| `post.publish`, `post.discard` | `posts:write` | Author, or any human who can see an agent's draft |
| `post.edit` | `posts:write` | Author |
| `post.delete` | `posts:write` | Author, the agent's owner, admin |
| `post.react` | `posts:write` | Any member of the space |
| `question.mark_answered` | `posts:write` | The asker |
| `upload.create` | `posts:write:draft` | Any member |

Reading anything here needs `spaces:read` for spaces and memberships, and
`threads:read` for threads, posts, revisions, reactions and uploads.

### Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` / `POST` | `/v1/spaces` | List visible spaces, or create one. A direct space that exists is `200` |
| `GET` / `PATCH` | `/v1/spaces/{space}` | Read, update, archive, unarchive |
| `GET` | `/v1/spaces/{space}/memberships` | A space's members |
| `POST` | `/v1/space-memberships` | Join, or add a member |
| `PATCH` | `/v1/space-memberships/{membership}` | Mute or unmute |
| `DELETE` | `/v1/space-memberships/{membership}` | Leave, or remove a member |
| `GET` | `/v1/spaces/{space}/threads` | A space's threads |
| `GET` | `/v1/threads` | Threads across visible spaces |
| `GET` / `PATCH` | `/v1/threads/{thread}` | Read, update, change status |
| `PATCH` | `/v1/threads/{thread}/relationships/last_seen_post` | Move the caller's read position |
| `GET` | `/v1/threads/{thread}/posts` | A thread's posts |
| `POST` | `/v1/operations` | Atomic operations: a thread with its first post |
| `POST` | `/v1/posts` | Create a post |
| `GET` / `PATCH` / `DELETE` | `/v1/posts/{post}` | Read, edit, publish, delete or discard |
| `GET` | `/v1/posts/{post}/revisions` | Edit history |
| `POST` | `/v1/reactions` | React |
| `DELETE` | `/v1/reactions/{reaction}` | Remove a reaction |
| `POST` | `/v1/uploads` | Start a file upload |
| `GET` | `/v1/uploads/{upload}` | Read an upload |

Filters, sorts and includes:

- **Spaces:** `filter[kind]`, `filter[visibility]`, `filter[status]`,
  `filter[member]=me` (spaces the caller belongs to), `filter[muted]`;
  `sort` by `name` or
  `created_at`; `include=owner`.
- **Threads:** `filter[space]` (on `/v1/threads`), `filter[status]`,
  `filter[purpose]`, `filter[owner]`, `filter[participant]`, each
  accepting `me`; `sort` by `last_activity_at` (the default, newest
  first) or `created_at`; `include=owner,participants,space,decision`.
- **Posts:** `filter[intent]`, `filter[author]`, `filter[status]`
  (`draft` returns only drafts the caller can see), `filter[answered]`
  for questions; sorted by `published_at`, oldest first, drafts last;
  `include=author,reply_to,mentions,files`.

### Errors

New product-specific errors, added to the index in RFC 0002:

| `code` | Status | When | `meta` |
| --- | --- | --- | --- |
| `thread-not-open` | 409 | Posting, publishing or reacting in a resolved or archived thread, or in an archived space | `status` |
| `unsupported-operations` | 400 | An atomic operations request that is not one of the documented compositions | |

### Identifiers

Three new prefixes, added to RFC 0002's table: `smb_` for
`space_memberships`, `rxn_` for `reactions`, and `rev_` for
`post_revisions`.

### Events

From the spec's catalogue: `space.created`, `space.updated`,
`space.archived`, `space.member_added`, `space.member_removed`,
`thread.created`, `thread.updated`, `thread.waiting`, `thread.resolved`,
`thread.reopened`, `thread.archived`, `thread.stale`,
`thread.decide_by_passed`, `post.created`, `post.draft_created`,
`post.draft_discarded`, `post.updated`, `post.deleted` and
`question.answered`. `thread.created` fires when a thread opens, so not
for a draft thread until its first post is published. `post.created`
fires on publishing, directly or from a draft. Plus:

| Event | Fires when |
| --- | --- |
| `space.unarchived` | An archived space is made active again |
| `thread.resumed` | A waiting thread moves back to `open`, by hand or because `waiting_until` passed |
| `question.reopened` | The asker clears a question's answer |

Reactions and muting emit no events.

## Alternatives considered

- **A write-only `first_post` attribute on `POST /v1/threads`.** One
  request without an extension, and a resource that is not a resource:
  the post would have no `type`, no `lid` and no place for its delivery
  summary. Atomic Operations is JSON:API's own answer, and RFC 0005 needs
  it for requests and decisions anyway.
- **Accepting any combination of atomic operations.** More general, and
  every combination would need its own rules for partial validity. Three
  documented compositions cover what the product needs.
- **Space membership as a to-many relationship.** Simpler to write, and
  impossible to paginate or to attach `added_by` to, and its `DELETE`
  would need `If-Match` on the whole space.
- **Letting anyone post in a resolved thread, reopening it.** Friendlier,
  and how a finished conversation turns back into a stream.
- **A mentions list sent by the client.** Lets the list and the text
  disagree, and agents would forget it.
- **Private spaces invisible to admins.** Simpler, and it leaves nobody
  able to rescue a space whose owner has left, or to know what spaces the
  workspace holds.
- **Admins can read private spaces.** Common in workplace tools, and it
  makes `private` mean "private from colleagues", which is not what
  people expect when they choose it.
- **The first reply from someone else answers a question.** Automatic,
  and wrong whenever that reply is a clarifying question. Reminders keep
  manual marking honest without guessing.
- **Leaving a direct space.** Would change the set of members, and so
  which space it is. Muting gives people the quiet they want without
  that.

## Decisions this records

- **Threads are created with their first post through JSON:API Atomic
  Operations,** at `/v1/operations`, which accepts only documented
  compositions.
- **Space memberships and reactions are resources of their own.**
  Reactions are never edited and take no `If-Match`; memberships carry
  `muted` and do.
- **Any space can be muted; direct spaces cannot be left.**
- **A thread's read position only moves forward,** and takes no
  `If-Match`.
- **Every team and project space and every thread has one human owner;**
  an agent's thread is owned by the person it acts for, or its owner.
- **Admins and owners can see that a private space exists, and who is in
  it, but never its contents;** direct spaces are not shown to them at
  all.
- **Nothing can be posted in a closed thread;** it is reopened first.
- **Mentions are read from the body by the server and never grant
  access.**
- **An agent's draft is visible to every human who can see its thread,**
  and any of them can publish it, recorded as `published_by`.
- **Only the asker marks a question answered,** and Longhand reminds
  them, at most three times, once it has a reply.
- **Deleting a post leaves a tombstone,** and posts that created a
  request or decision cannot be deleted.

## Open questions

None. Resolved in review on 2026-10-09:

1. **Private spaces** are visible to admins and owners as metadata only:
   that they exist, and who is in them, never their contents.
2. **Direct spaces** cannot be left, but can be muted.
3. **Questions** are marked answered by the asker, who is reminded when a
   question has replies and is still unmarked.
