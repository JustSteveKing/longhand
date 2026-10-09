# RFC 0007: Briefs

- **Status:** Accepted
- **Created:** 2026-10-09
- **Depends on:** RFC 0002, RFC 0003, RFC 0004, RFC 0006, ADR 0012, ADR 0022, ADR 0024
- **Amended by:** RFC 0008, API contract review, 2026-10-09

## Summary

A brief is a generated catch-up for one reader over a defined scope. It
is structured data with a citation on every item, so a client can link
each line to what it came from and the reader can check anything that
looks wrong. This RFC defines requesting a brief, the brief and its
items, the citation rules, how a generator gets the material it may use,
how a team brings its own generator, staleness, and feedback. It is
section 9 of [the spec](../spec.md).

## Problem

The spec's principle is right: a brief only draws on what the reader can
already see, whoever generates it. What it leaves unsaid is how that is
enforced. A bring-your-own generator is an agent with its own scopes and
space allow-list, which are not the reader's, so "it reads the scope
through the API or MCP" either shows it too little (its own view) or
needs it to impersonate the reader.

The shape needs work too. Citations are resource IDs inside attributes,
feedback is addressed by an item's position in an array
(`/items/{index}/feedback`), and generators write with a `PUT`, which
JSON:API does not have. Check-ins (RFC 0008) post a brief into a thread
for everyone there, which the one-reader model does not cover.

## Goals

1. Every item in every brief cites at least one source, and every source
   is something the reader can already see.
2. No generator, built in or brought, ever sees more than the reader
   could, and sees only what one brief needs.
3. A brief never changes silently. When what it cites changes, it says
   so.
4. A team can replace the built-in generator without Longhand changing
   how briefs are requested, validated or read.
5. Readers can tell a generator it was wrong, and the person accountable
   for that generator hears about it.

## Non-goals

- Who pays for generation, and which model the built-in generator uses.
  RFC 0001 deferred that to its own RFC; until then the reference
  implementation's model is deployment configuration.
- Briefs in end-to-end encrypted spaces, which v1 does not have.
- Briefs that act: a brief proposes nothing and changes nothing.
- Translating briefs.

## Design

### Requesting a brief

```http
POST /v1/briefs
Content-Type: application/vnd.api+json
Idempotency-Key: 9b2e...

{
  "data": {
    "type": "briefs",
    "attributes": {
      "scope": "member",
      "since": "last_seen",
      "focus": ["decisions", "requests_for_me", "blocked"],
      "length": "short"
    }
  }
}
```

The response is `202 Accepted` with the brief, in status `queued`, and a
`Location` to poll, per RFC 0002's long-running work. `brief.ready` or
`brief.failed` says when it is done.

| `scope` | Covers | Also needs |
| --- | --- | --- |
| `member` | Everything the reader can see across their spaces | `since` |
| `space` | One space | The `space` relationship; `since` |
| `thread` | One thread, from its start | The `thread` relationship |
| `decisions` | The decision log | The `space` relationship, optional; `since` |

- **`since`** is a timestamp or `last_seen`. `last_seen` means, for each
  thread, from the reader's read position in it (ADR 0024), and for a
  thread they have never opened, from when they joined its space. A
  brief covers at most 30 days; an earlier `since` is moved up to that,
  and the brief's `source_window` says what was actually covered.
- **`focus`** narrows the brief to any of `decisions`, `requests_for_me`,
  `requests_from_me`, `blocked`, `questions_unanswered`, `updates` and
  `mentions`. Omitting it includes them all.
- **`length`** is `short` (up to 10 items), `standard` (up to 25) or
  `full` (up to 100).
- Each member may request a set number of briefs a day, roll-ups
  included; more is `429` `rate-limit-exceeded`. The number is the
  workspace's `brief_daily_limit`, 30 by default, from 1 to 500, set by
  owners and admins. Generation costs money, and the workspace is who
  knows how much it wants to spend until the RFC on who pays says more.

Requesting needs `briefs:write`, as action `brief.request`.

### The brief

```json
{
  "type": "briefs",
  "id": "brf_01JABC...",
  "attributes": {
    "status": "ready",
    "scope": "member",
    "since": "last_seen",
    "focus": ["decisions", "requests_for_me", "blocked"],
    "length": "short",
    "audience": "reader",
    "sections": [
      { "kind": "decisions", "heading": "Decided while you were away" }
    ],
    "source_window": { "from": "2026-10-07T16:00:00Z", "to": "2026-10-08T11:40:00Z" },
    "model": { "provider": "anthropic", "name": "claude-sonnet-5-5" },
    "failure": null,
    "stale": false,
    "stale_since": null,
    "created_at": "2026-10-08T11:41:12Z",
    "ready_at": "2026-10-08T11:41:40Z"
  },
  "relationships": {
    "reader": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "requested_by": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "generator": { "data": { "type": "members", "id": "mem_01JA8B..." } },
    "space": { "data": null },
    "thread": { "data": null },
    "items": { "links": { "related": "/v1/briefs/brf_01JABC.../items" } }
  }
}
```

A brief's items are resources of their own, `brief_items`:

```json
{
  "type": "brief_items",
  "id": "bit_01JABD...",
  "attributes": {
    "section": "decisions",
    "position": 1,
    "text": "Billing queue moves to SQS."
  },
  "relationships": {
    "brief": { "data": { "type": "briefs", "id": "brf_01JABC..." } },
    "citations": {
      "data": [
        { "type": "decisions", "id": "dec_01JAB0..." },
        { "type": "posts", "id": "pst_01JAAZ..." }
      ]
    }
  }
}
```

Citations are a relationship, so they can be included
(`GET /v1/briefs/{brief}?include=items.citations`), and an item has its
own ID for feedback to point at. `section` is one of the brief's
`sections`, and `position` orders items inside it. An item's `text` is
plain text, up to 500 characters: links belong in citations, not prose.
It is `null` to anyone who may read the item but not see every one of
its citations, such as a generator's owner following a flag (below).

**Status** moves from `queued` to `generating` when the generator starts,
then to `ready` or `failed`. A brief that is not `ready` within 10 minutes
of being requested fails with `failure.code` `generator_timeout`. Other
failure codes are `generator_error`, reported by the generator, and
`validation`, when a submission was rejected too often (below).

**Who can read it.** A brief with `audience: "reader"` is visible to its
reader, and to an agent acting on their behalf, and to nobody else,
admins included, because it is built from the reader's view. While it is
`generating`, the generator can read it too. A roll-up, with
`audience: "thread"`, is readable by everyone who can see its thread
(below).

### The source bundle

A generator never reads the workspace with its own permissions. When a
brief is requested, Longhand works out its **source bundle**: every
thread, post, request and decision inside the brief's scope and source
window that the reader can see, evaluated with the reader's permissions
at that moment, and narrowed by `focus`.

- The generator reads the bundle at `GET /v1/briefs/{brief}/sources`,
  a paginated, heterogeneous collection of those resources, oldest first,
  with `include` for their authors and threads.
- Only the brief's assigned generator can read it, and only while the
  brief is `generating`. To anyone else, and afterwards, it is `404`.
- The bundle is fixed when the brief starts generating. Something
  published after that is not in it.
- Reading the bundle is the only access the generator gets. It is not a
  grant on the threads themselves; a generator that has no access of its
  own to a space still cannot read that space through the API.
- Every read of a bundle is in the audit log, with the brief, the reader
  and the generator.

This is how the reader's permissions bound the generator, rather than
the generator's: the generator sees exactly what one brief may use and
nothing else, and the citation rules can be checked against one list.

### Citation rules

- Every item has at least one citation, whoever generated it.
- Every citation is a resource in the brief's source bundle. That one
  rule covers existing, being inside the scope and being visible to the
  reader.
- Citations can be posts, requests, decisions and threads.
- A submission that breaks either rule is rejected whole with `422`
  `uncited-content`, one error per failing item, each with a
  `source.pointer` to it. Nothing partial is ever stored.

### Generating

The workspace's brief generator is an agent member, named on the
workspace as its `brief_generator` relationship (RFC 0003's workspace),
which owners and admins change.

1. Longhand fires `brief.requested`, delivered only to the generator,
   with the brief.
2. The generator moves the brief to `generating` with a `PATCH` of
   `status`, which fixes the bundle.
3. It reads the bundle, writes the brief, and submits it in one
   `POST /v1/operations` request (ADR 0022): an `update` of the brief,
   setting `sections`, `model` and `status: "ready"`, with its ETag in
   `meta.if_match` (ADR 0035), followed by an `add` for each item.
4. Longhand validates the whole submission: the citation rules, the
   length limit, every item's `section`. If it passes, the brief is
   `ready` and `brief.ready` fires. If not, nothing is stored and the
   brief stays `generating`, so the generator can correct it.
5. A generator that cannot finish sets `status` to `failed` with
   `failure.code` `generator_error` and a `failure.detail`. After five
   rejected submissions, the brief fails with `failure.code`
   `validation`.

Generating needs `briefs:write`, as actions `brief.generate` and
`brief.submit`, and is only possible for the brief's assigned generator.
To any other member, a brief they are not the reader of is `404` (ADR
0012).

**The built-in generator** is an agent created with every workspace,
named `Longhand`, owned by the workspace's creator and transferable like
any agent (RFC 0003), with `briefs:write` and nothing else. It works
exactly as a brought generator does: the same event, bundle, submission
and validation, through the same Actions. Nothing it does is possible
only because it is built in.

**Bringing your own** means creating an agent with `briefs:write`,
subscribing it to `brief.requested` (RFC 0010), and making it the
workspace's `brief_generator`. The workspace picks the model and pays
for it. The brief's `generator` and `model` always say who and what wrote
it.

### Staleness

A brief is a snapshot. It never changes after it is `ready`, but it says
when what it cites has:

- A cited post is edited or deleted, a cited decision is superseded, or a
  cited request changes state: the brief becomes `stale`, with
  `stale_since`, and `brief.stale` fires once.
- The reader loses access to a cited resource: that citation is left out
  of the brief for them (ADR 0012), an item left with no visible
  citations is left out entirely, and the brief becomes `stale`.

New activity since the brief does not make it stale. That is what
requesting another one is for.

### Feedback

A reader tells the generator how an item did with a `brief_feedback`
resource:

```json
{
  "type": "brief_feedback",
  "id": "bfb_01JABF...",
  "attributes": { "rating": "inaccurate", "note": "This was reversed on Tuesday" },
  "relationships": {
    "item": { "data": { "type": "brief_items", "id": "bit_01JABD..." } }
  }
}
```

- `rating` is `accurate`, `inaccurate` or `missing_context`; `note` is
  optional, up to 1,000 characters.
- Created with `POST /v1/brief-feedback`, as action `brief.feedback`,
  only by the reader. A reader has one feedback per item, which they can
  change with a `PATCH` or remove.
- `inaccurate` fires `brief.flagged` and gives a `brief_flagged` inbox
  item (RFC 0006) to whoever answers for the generator: its owner, or for
  the built-in generator, every owner of the workspace, since the
  built-in generator is the workspace's choice rather than one person's.
  Each of them sees the rating, the note and the generator, and sees the
  item's text only if they can see every one of its citations; otherwise
  the text stays with the reader, because it was built from the reader's
  view.
- The generator can list feedback on the briefs it wrote, with
  `GET /v1/brief-feedback?filter[generator]=me`, under the same rule for
  item text.
- One piece of feedback is read at `GET /v1/brief-feedback/{feedback}`,
  by whoever gave it, by the brief's generator, and by whoever answers
  for the generator, under the same rule for item text. To anyone else it
  is `404`.

### Rolling up a thread

Anyone who can see a thread can **roll it up**: ask for one summary of
the whole thread, for everyone who can see it. A long thread then opens
on its summary, and anyone who wants the detail clicks through to the
full thread.

A roll-up is a brief with `scope: "thread"` and `audience: "thread"`,
requested with `POST /v1/briefs` as action `thread.roll_up`:

- **Its bundle is the thread,** as any member of its space sees it, never
  including drafts. A thread looks the same to everyone who can see it,
  so one bundle and one brief serve them all.
- **Its `reader` is `null`.** Anyone who can see the thread can read it,
  and give feedback on it, one each per item. `requested_by` records who
  rolled it up.
- **The thread's `summary` relationship** (added to RFC 0004's thread)
  points at its latest ready roll-up. A new roll-up replaces it as the
  summary once it is ready; earlier ones stay readable from
  `GET /v1/briefs?filter[thread]=`.
- **One at a time.** Rolling up a thread that already has a roll-up
  queued or generating returns that one with `200 OK` instead of starting
  another.
- **What it covers** is in its `source_window`. The thread's
  `meta.posts_since_summary` counts posts published after it, so a client
  can show that the summary is behind and offer to roll it up again. New
  posts do not make it stale; edits, deletions and changes to what it
  cites do, as for any brief.
- **It notifies nobody.** `brief.ready` goes to the stream and webhooks
  for those who can see the thread, and nothing reaches an inbox.
- **It counts against the daily limit** of the member who rolled it up,
  except when a check-in asks for it (RFC 0008).

Check-ins (RFC 0008) roll up each run's thread when the run closes.

### Actions

| Action | Scope | Who |
| --- | --- | --- |
| `brief.request` | `briefs:write` | Any member, for themselves |
| `thread.roll_up` | `briefs:write` | Anyone who can see the thread |
| `brief.generate`, `brief.submit`, `brief.fail` | `briefs:write` | The brief's generator |
| `brief.feedback` | `briefs:read` | The reader, or anyone who can see a roll-up's thread |
| `workspace.update` (for `brief_generator` and `brief_daily_limit`) | `workspace:write` | Owner, admin |

Reading a brief and its items needs `briefs:read`.

### Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/v1/briefs` | Request a brief |
| `GET` | `/v1/briefs` | The caller's briefs, newest first |
| `GET` / `PATCH` | `/v1/briefs/{brief}` | Read; as the generator, start or fail it |
| `GET` | `/v1/briefs/{brief}/items` | Its items, in order |
| `GET` | `/v1/briefs/{brief}/sources` | The source bundle, for the generator while generating |
| `POST` | `/v1/operations` | Submit a generated brief |
| `GET` / `POST` | `/v1/brief-feedback` | List or give feedback |
| `GET` / `PATCH` / `DELETE` | `/v1/brief-feedback/{feedback}` | Read, change or remove feedback |

`GET /v1/briefs` lists the caller's own briefs, and with
`filter[thread]` the roll-ups of a thread they can see. It also filters by
`filter[status]`, `filter[scope]` and `filter[stale]`.

### Errors and identifiers

No new error codes: `uncited-content` (RFC 0002) now points at failing
items with `source.pointer` rather than listing indexes in `meta.items`,
and RFC 0002's index changes to match.

Two new prefixes, added to RFC 0002's table: `bit_` for `brief_items` and
`bfb_` for `brief_feedback`.

### Events

From the spec's catalogue: `brief.requested` (only ever to the brief's
generator), `brief.ready`, `brief.failed` (with `failure`) and
`brief.flagged`. Plus:

| Event | Fires when |
| --- | --- |
| `brief.stale` | A ready brief becomes stale |

`brief.ready`, `brief.failed` and `brief.stale` go only to the reader and
agents acting for them, or for a roll-up, to those who can see the
thread.

## Alternatives considered

- **The generator reads the workspace with the reader's permissions.**
  Lets it see everything the reader can, not only what one brief needs,
  for as long as the token lasts.
- **The generator reads with its own permissions.** The spec's wording,
  and either a brief built from the wrong view or a generator with access
  to every space.
- **Citations as IDs in an attribute.** Cannot be included, cannot be
  hidden when the reader loses access, and feedback has to address items
  by position.
- **Updating a brief in place when its sources change.** The reader
  could never be sure what they read was what is there now. Stale says
  so instead.
- **Posting a roll-up into the thread as a post.** Puts the summary at
  the bottom of what it summarises, and makes each new roll-up another
  post to read.
- **Storing what validates and dropping the rest.** A brief with gaps
  nobody can see is worse than a submission the generator has to fix.

## Decisions this records

- **A generator reads only a source bundle,** worked out with the
  reader's permissions and fixed when generation starts.
- **Every citation must be in the bundle,** and a submission is accepted
  whole or not at all.
- **Brief items and feedback are resources,** and citations are a
  relationship.
- **Generators submit through Atomic Operations,** and there is no `PUT`.
- **The built-in generator is an ordinary agent** with no special path.
- **Briefs are snapshots that become stale,** never updated in place.
- **A reader's brief is visible only to the reader,** admins included.
- **Flagged items reach the generator's owner without leaking the
  reader's view.**
- **Anyone who can see a thread can roll it up** into one summary for
  everyone who can see it, which becomes the thread's `summary`.
- **Flags on the built-in generator go to every owner.**
- **The daily brief limit is a workspace setting,** 30 by default.

## Open questions

None. Resolved in review on 2026-10-09:

1. **Thread briefs for everyone** become roll-ups: anyone who can see a
   thread can roll it up, and everyone who can see it reads the summary
   or clicks through to the full thread.
2. **Flags on the built-in generator** go to every owner.
3. **The daily limit** is a workspace setting.
