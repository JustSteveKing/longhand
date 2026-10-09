# RFC 0006: The attention model

- **Status:** Accepted
- **Created:** 2026-10-09
- **Depends on:** RFC 0003, RFC 0004, RFC 0005, ADR 0011, ADR 0022
- **Amended by:** RFC 0010, API contract review, 2026-10-09

## Summary

The sender chooses how urgent something is, the recipient decides what
may interrupt them, and the server enforces the recipient's rules. That
split is what makes Longhand async. This RFC defines availability, the
four urgency tiers, who receives what, how each recipient's delivery
time is worked out, the inbox that holds only what needs a person to act,
and the digest that carries what they should know but need not act on.
It is section 8 of [the spec](../spec.md), plus the routing that RFCs
0004 and 0005 deferred to it.

## Problem

The spec's delivery rules are sound, and they leave the hardest part
open: who the recipients are. RFC 0004 says participants receive a
thread's updates, the spec says `fyi` never creates inbox items, and
mentions, muting, decisions and the reasons added by RFCs 0004 and 0005
all need placing in one routing table.

The spec also has shapes that do not fit what has been decided since:
`PUT /v1/me/availability`, an away period that embeds a member ID, an
`interrupt_for` list where only one value means anything, a cron string
for the digest, and five inbox action endpoints, including a bulk one.
Bulk changes expose a gap in ADR 0022: an `update` inside an atomic
operations request has nowhere to send `If-Match`.

## Goals

1. Nothing reaches a person outside their working hours or during a focus
   block unless it is an incident they have agreed to be woken for.
2. The sender always knows when each recipient will see what they sent,
   before and after sending.
3. The inbox holds only what needs the member to act, each item with a
   reason, and closes itself when the thing is done.
4. What a member should know but need not act on reaches them in one
   scheduled digest, not as a stream.
5. A member's exact hours stay private; what others need, whether they
   are working and when a message would land, is public.

## Non-goals

- Calendar integrations that manage availability. Availability is set by
  hand in v1.
- Push notifications to mobile devices. The web app and email are the v1
  channels; `inbox.item_delivered` on the stream (RFC 0010) is the hook
  for any other client.
- Workspace analytics, including the spec's count of `now` sends per
  sender.
- Per-thread notification settings beyond muting a space (RFC 0004).

## Design

### Availability

Every human member has one availability, set by hand:

```json
{
  "type": "availabilities",
  "id": "avl_01JA7S...",
  "attributes": {
    "timezone": "Europe/London",
    "working_hours": [
      { "days": ["mon", "tue", "wed", "thu"], "start": "09:30", "end": "16:00" }
    ],
    "focus_blocks": [
      { "days": ["tue", "thu"], "start": "09:30", "end": "12:00" }
    ],
    "response_expectation": "PT24H",
    "today_batches": ["09:30", "14:00"],
    "digest": { "days": ["mon", "tue", "wed", "thu", "fri"], "time": "09:00" },
    "email": { "digest": true, "batches": false, "incidents": true },
    "incidents_interrupt": true,
    "in_window": false,
    "next_window_starts_at": "2026-10-09T08:30:00Z",
    "lands_at": {
      "incident": "2026-10-08T21:14:00Z",
      "now": "2026-10-09T08:30:00Z",
      "today": "2026-10-09T08:30:00Z",
      "digest": "2026-10-10T08:00:00Z"
    }
  },
  "relationships": {
    "member": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "away_periods": { "links": { "related": "/v1/members/mem_01JA7Q.../away-periods" } }
  }
}
```

- **`timezone`** is an IANA name. It is the member's timezone everywhere;
  the `timezone` on the member (RFC 0003) is a read-only copy of it.
- **`working_hours`** are windows in local time, one to fourteen of them.
  A window whose `end` is before its `start` crosses midnight. A human
  always has at least one window; a new member starts with Monday to
  Friday, 09:00 to 17:00, in the timezone taken at sign-up.
- **`focus_blocks`** are windows inside working hours when only an
  incident may interrupt.
- **`response_expectation`** is an ISO 8601 duration, shown to anyone
  sending to this member, so the norm is visible instead of assumed.
- **`today_batches`** are up to six local times when `today` items
  arrive. With none, `today` behaves like `now`.
- **`digest`** sets when the digest is made: days and a local time.
- **`email`** sets what is also sent by email (below).
- **`incidents_interrupt`** replaces the spec's `interrupt_for`, in which
  only `incident` ever meant anything. It is on by default and each
  member opts out in their own settings. The web app shows it, already
  on, when a member first sets their working hours, so the default is
  one they have seen rather than one they discover at three in the
  morning.
- **`in_window`**, **`next_window_starts_at`** and **`lands_at`** are
  computed. `lands_at` says when something sent now at each tier would
  reach this member, before muting or space caps are applied. It is what
  a client shows while someone is writing ("lands at 09:30 their time").

Availability is changed with a `PATCH` by the member themselves, as
action `availability.update`, with `If-Match`. An array attribute in a
`PATCH` replaces the whole array, as JSON:API defines. It needs
`availability:write`, and an agent never changes a person's
availability, including its principal's.

**What others see.** To anyone else, availability has `meta.access` set
to `"limited"` and only `timezone`, `response_expectation`, `in_window`,
`next_window_starts_at`, `lands_at` and the current or next away period.
Exact hours, focus blocks, batches, digest and email settings, and
`incidents_interrupt` stay private: they are left out of the limited
view's attributes, not set to `null`, so the two views are told apart by
`meta.access` and by which attributes are present.
This is the pattern ADR 0025 set for private spaces: one resource type,
with what the caller may see.

**Agents** have no availability of their own. They are always in a
window, have no focus blocks, batches or digest, and everything reaches
them immediately. Their availability resource reports exactly that:
empty `working_hours`, `focus_blocks` and `today_batches`, a `null`
`digest`, `in_window` always `true`, and `lands_at` of now for every
tier. It cannot be changed: a `PATCH` of an agent's availability is
`403` `insufficient-scope`, and so is an agent adding an away period.

### Away periods

An away period is a resource of its own, `away_periods`, because it has a
member to delegate to:

```json
{
  "type": "away_periods",
  "id": "awy_01JAC4...",
  "attributes": {
    "starts_on": "2026-10-20",
    "ends_on": "2026-10-24",
    "note": "Conference"
  },
  "relationships": {
    "member": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "delegate": { "data": { "type": "members", "id": "mem_01JA7R..." } }
  }
}
```

- Dates are local to the member and inclusive. Away periods cannot
  overlap: creating or changing one so that it overlaps another of the
  member's is `409` `resource-conflict`. Invalid dates, such as an end
  before the start, are `422` `validation-failed`.
- The member creates, changes and deletes their own, with
  `availability:write`, as action `availability.update`.
- While a member is away, everything to them is held until their first
  working window after `ends_on`, unless it is an incident they allow.
- A **delegate** is a suggestion, never an automatic reassignment. A
  request to someone who is away, or who will be when it lands, comes
  back with the delegate in the delivery summary as `suggested_delegate`,
  so the requester can reassign it deliberately (RFC 0005).
- Away periods are public, delegate and note included: the note is what
  tells a colleague why.

### Urgency tiers

| Tier | Delivery | Limits |
| --- | --- | --- |
| `incident` | Immediately, including outside working hours, during focus blocks and while away, if the recipient has `incidents_interrupt`; otherwise as `now` | Only in `incident` threads (`422` `incident-only`). 20 per sender per hour (`429` `rate-limit-exceeded`) |
| `now` | Immediately inside a working window and outside a focus block. Otherwise when the next such time starts | None |
| `today` | At the recipient's next batch time inside a working window | None |
| `digest` | Only in the next digest and in briefs | None |

A post's tier is chosen by the sender, defaults to the space's
`default_urgency`, and is capped at its `max_urgency` (RFC 0004).
Items Longhand raises by itself (an overdue request, a stale thread, a
reminder) are `today`.

### Who receives what

Delivery is about inbox items: each one tells one member that something
needs them. Recipients come from what happened, never from the stream of
posts. The author or actor never receives their own item, and nobody
receives anything about a thread they cannot see.

| What happened | Who gets an item | Reason |
| --- | --- | --- |
| `fyi` post | Each mentioned member, at `digest` tier whatever the post's tier | `mentioned` |
| `question` post | Each mentioned member; if nobody is mentioned, the thread owner | `question_asked` |
| `request` post | The assignee | `request_assigned` |
| `update` post | The thread owner | `update_posted` |
| `decision` post | Nobody; participants see it in their digest | |
| Any other mention in a `question`, `request`, `update` or `decision` post | The mentioned member, if no item above covers them | `mentioned` |
| A request reassigned | The new assignee | `request_assigned` |
| A request completed or declined | The requester | `request_completed`, `request_declined` |
| A request overdue | The assignee and the requester | `request_overdue` |
| A request's assignee loses access | The requester | `request_needs_reassignment` |
| A new due date proposed | The requester | `due_by_proposed` |
| A question with replies left unmarked | The asker | `question_unmarked` |
| A `decision` thread passes `decide_by` | The thread owner | `decision_needed` |
| An agent's draft waiting | The agent's owner, or its principal when delegated | `draft_awaiting_approval` |
| A thread goes stale | The thread owner | `thread_stale` |
| A thread reopened | The thread owner | `thread_reopened` |
| A check-in run opens | Each respondent | `check_in_due` |
| A brief item flagged inaccurate | The generating agent's owner | `brief_flagged` |
| A webhook disabled | The subscription's creator | `webhook_disabled` |
| A member's subscription waiting for approval | Every owner and admin | `subscription_pending` |
| A pending subscription approved or deleted | The subscription's creator | `subscription_reviewed` |

An `fyi` post never interrupts anyone. A mention in one is still worth
knowing about, so it becomes a `mentioned` item at `digest` tier, which
reaches the member in their next digest and never before; the way to get
someone's attention sooner is to ask them something.

**Muting** (RFC 0004) removes a member from every row above for that
space except `request_assigned`, `request_overdue` and `incident` posts,
which are commitments and emergencies rather than chatter.

**One item per subject.** A member has at most one open item for a given
reason and subject. A second mention in the same post, or a repeated
reminder, updates the open item rather than adding another.

### Working out delivery

For each recipient, Longhand works out one `deliver_at`, in this order:

1. If they cannot see the thread, nothing is delivered: `no_access`.
2. If they have muted the space and the item is not exempt, nothing is
   delivered: `muted`.
3. If the tier is `incident` and they allow it, deliver now:
   `incident_override`.
4. If they are away, hold until their first working window after the
   away period: `away`.
5. For `now`: deliver now if inside a working window and outside a focus
   block (`in_window`); otherwise when the next such time starts
   (`outside_working_hours` or `focus_block`).
6. For `today`: deliver at the next batch time that falls inside a
   working window: `batched`.
7. For `digest`: attach to the next digest, with no notification:
   `digest`.

The function is pure: availability, tier, the space's cap and the current
time in, `deliver_at` and a reason out. It is the same function behind
`lands_at` on availability and the `delivery` summary returned when a
post is created (RFC 0004):

```json
"delivery": [
  { "member": "mem_01JA7R...", "tier": "today", "deliver_at": "2026-10-09T08:30:00Z", "reason": "batched" },
  { "member": "mem_01JA7T...", "tier": "today", "deliver_at": "2026-10-27T09:00:00Z", "reason": "away", "suggested_delegate": "mem_01JA7R..." }
]
```

Sending outside someone's hours is never an error. The response is
`201` with the summary, and the client shows it. A sender who genuinely
needs to break through uses an incident thread.

**Changing plans.** Items not yet delivered are scheduled, not sent. If
the recipient's availability or away periods change before `deliver_at`,
every scheduled item is worked out again under the new rules, so
coming back early from holiday brings the queue forward and adding a
focus block pushes it back.

### The inbox

The inbox replaces unread counts and mention badges. It holds only items
that need the member to do something.

```json
{
  "type": "inbox_items",
  "id": "inb_01JAB5...",
  "attributes": {
    "reason": "request_assigned",
    "tier": "today",
    "state": "open",
    "due_by": "2026-10-10T12:00:00Z",
    "deliver_at": "2026-10-09T08:30:00Z",
    "delivered_at": "2026-10-09T08:30:00Z",
    "snoozed_until": null,
    "done_at": null,
    "done_cause": null
  },
  "relationships": {
    "member": { "data": { "type": "members", "id": "mem_01JA7R..." } },
    "subject": { "data": { "type": "requests", "id": "req_01JAA4..." } },
    "thread": { "data": { "type": "threads", "id": "thr_01JAA1..." } },
    "from": { "data": { "type": "members", "id": "mem_01JA7Q..." } }
  }
}
```

The spec's embedded `subject`, `thread` and `from` objects are
relationships. `subject` is whatever the item is about: a request, post,
thread, decision, brief, check-in run or subscription.

**States.**

| From | To | How | Who |
| --- | --- | --- | --- |
| `scheduled` | `open` | `deliver_at` arrives; `inbox.item_delivered` fires | Longhand |
| `open`, `snoozed` | `done` | `PATCH` `state`, as `inbox.done`; or automatically | The member |
| `open` | `snoozed` | `PATCH` `state` with `snoozed_until`, up to 90 days ahead, as `inbox.snooze` | The member |
| `snoozed` | `open` | `snoozed_until` arrives | Longhand |
| `done`, `snoozed` | `open` | `PATCH` `state`, as `inbox.reopen` | The member |

- A `scheduled` item is invisible to its member until it is delivered.
  The sender knows when it will land; the recipient is not shown it
  early.
- A snoozed item returns exactly when the member asked, without passing
  through their availability again; choosing the time was the point.
- **Items close themselves** when what they are about is settled: a
  request accepted, declined, completed, cancelled or reassigned away; a
  question marked answered; a draft published or discarded; a thread
  resolved or archived; access to the thread lost; a check-in run closed
  (RFC 0008). `done_cause` says which, and `inbox.item_done` carries it.
  Its values are `member` (closed by hand), `request_accepted`,
  `request_declined`, `request_completed`, `request_cancelled`,
  `request_reassigned`, `question_answered`, `draft_published`,
  `draft_discarded`, `thread_resolved`, `thread_archived`,
  `access_lost` and `run_closed`, and it is `null` while the item is
  open or snoozed.

**Reading the inbox.** `GET /v1/inbox-items` returns the caller's
delivered items, or their principal's for an agent acting on someone's
behalf (RFC 0003). It is ordered by tier, most urgent first, then
`due_by`, soonest first, then `delivered_at`, newest first. Filters are
`filter[state]` (default `open`), `filter[tier]`, `filter[reason]` and
`filter[thread]`; `include=subject,thread,from`. Nobody can read anyone
else's inbox, admins included.

**Bulk changes** use `POST /v1/operations` with up to 100 `update`
operations on the caller's inbox items, each changing `state`. This is a
documented composition under ADR 0022, replacing the spec's
`/v1/me/inbox/bulk`.

### Version checks inside atomic operations

An atomic operations request has one set of headers, so `If-Match`
cannot say which version each `update` expects. ADR 0011 still applies
to every one of them. Each `update` or `remove` operation therefore
carries the ETag it expects in its own `meta` (ADR 0035):

```json
{
  "op": "update",
  "ref": { "type": "inbox_items", "id": "inb_01JAB5..." },
  "data": { "type": "inbox_items", "id": "inb_01JAB5...", "attributes": { "state": "done" } },
  "meta": { "if_match": "\"7f3a9c\"" }
}
```

A missing `meta.if_match` is `428` `precondition-required` and a stale one
`412` `precondition-failed`, each with a `source.pointer` to the
operation, and like any failure they undo the whole request. This also
applies to the thread `update` in RFC 0005's composition that resolves a
decision thread.

### Digests

The digest carries what a member should know and need not act on, once,
on their schedule:

- inbox items delivered at the `digest` tier since the last digest;
- decisions recorded, and threads resolved, in threads they participate
  in;
- threads they participate in with new published posts, with a count
  for each.

Each one is a `digests` resource, readable in the web app at
`GET /v1/digests`, and sent by email unless the member has turned
`email.digest` off. A digest
with nothing in it is not made. Making one fires `digest.sent`. Agents
have no digest; they have the stream and webhooks.

```json
{
  "type": "digests",
  "id": "dig_01JAD2...",
  "attributes": {
    "period_starts_at": "2026-10-08T08:00:00Z",
    "period_ends_at": "2026-10-09T08:00:00Z",
    "emailed": true,
    "created_at": "2026-10-09T08:00:00Z"
  },
  "relationships": {
    "member": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "inbox_items": { "data": [] },
    "decisions": { "data": [{ "type": "decisions", "id": "dec_01JAB0..." }] },
    "resolved_threads": { "data": [] },
    "active_threads": {
      "data": [{ "type": "threads", "id": "thr_01JA9X...", "meta": { "new_posts": 4 } }]
    }
  }
}
```

Each active thread's count of new posts rides in the `meta` of its
resource identifier, which JSON:API allows, so the thread stays a
relationship. A thread the member can no longer see is left out of every
part.

Generated summaries are briefs (RFC 0007), not digests. A digest lists;
it never summarises.

### Email

Email is the only channel outside the app in v1. Each member chooses
what it carries, in their availability's `email`:

| Setting | Default | Sends |
| --- | --- | --- |
| `digest` | On | Each digest, as it is made |
| `batches` | Off | At each `today` batch time, the items delivered in that batch, if there are any |
| `incidents` | On | An `incident` item the moment it is delivered, when `incidents_interrupt` is on |

Batches are off by default because the inbox is the product: a person who
keeps the app open would get everything twice. They are there for the
person who does not, who would otherwise only hear from Longhand once a
day. `now` items are never emailed one by one; they arrive in the next
batch email, so email never becomes the stream that the inbox replaced.
Emails go to the account's verified address, list items with links into
the web app, and never include the content of private spaces beyond
titles and the item's reason. Agents get no email.

### Actions

| Action | Scope | Who |
| --- | --- | --- |
| `availability.update` | `availability:write` | The member, for their own availability and away periods |
| `inbox.done`, `inbox.snooze`, `inbox.reopen` | `inbox:write` | The member, or an agent acting on their behalf |

Reading another member's limited availability and away periods needs
`members:read`; reading one's own full availability needs
`availability:write`. Reading inbox items and digests needs
`inbox:read`.

### Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/v1/members/{member}/availability` | A member's availability, limited unless it is the caller's |
| `GET` / `PATCH` | `/v1/availabilities/{availability}` | Read or change availability |
| `GET` | `/v1/members/{member}/away-periods` | A member's current and upcoming away periods, soonest first |
| `POST` | `/v1/away-periods` | Add an away period |
| `GET` / `PATCH` / `DELETE` | `/v1/away-periods/{away_period}` | Read, change or remove one |
| `GET` | `/v1/inbox-items` | The caller's inbox |
| `GET` / `PATCH` | `/v1/inbox-items/{item}` | Read, mark done, snooze, reopen |
| `POST` | `/v1/operations` | Bulk inbox changes |
| `GET` | `/v1/digests` | The caller's digests, newest first |
| `GET` | `/v1/digests/{digest}` | One digest |

### Errors and identifiers

No new error codes. Incident limits are `429` `rate-limit-exceeded`;
invalid windows, batches or snoozes are `422` `validation-failed` with a
pointer.

Three new prefixes, added to RFC 0002's table: `avl_` for
`availabilities`, `awy_` for `away_periods` and `dig_` for `digests`.

### Events

From the spec's catalogue: `availability.changed` (with the limited view
only), `inbox.item_delivered` (only ever to the item's member, or an
agent acting for them), `inbox.item_done` and `digest.sent`. Plus:

| Event | Fires when |
| --- | --- |
| `inbox.item_snoozed` | A member snoozes an item |
| `inbox.item_reopened` | A snoozed or done item opens again, by hand or because its snooze ended |

`inbox.item_delivered` is the only event a client may raise a
notification from.

## Alternatives considered

- **Unread counts per thread.** What the product exists to replace.
- **Recipients from participation.** Telling every participant about
  every post is the stream again. Participants hear about outcomes in
  their digest; the inbox is for what needs them.
- **Delivering at send time and filtering on the client.** Puts the
  recipient's rules in every client, and lets a badly behaved one ignore
  them. The server holds what is not due.
- **Showing scheduled items to the recipient early.** Undoes the point of
  scheduling them.
- **A cron expression for the digest.** Powerful, and no person should
  have to write one to say "weekdays at nine".
- **Mentions in `fyi` posts create nothing.** Keeps `fyi` absolute, and
  loses the one thing a mention is for: making sure someone knows.
- **Emailing every delivered item.** Rebuilds the stream in the one place
  people cannot mute it.
- **Exempting inbox items from version checks** so bulk changes work.
  Simpler, and it would leave every `update` inside atomic operations
  unprotected, the decision-thread resolution included.

## Decisions this records

- **Delivery is worked out by one pure function,** shared by the delivery
  summary, `lands_at` and the scheduler.
- **Recipients come from what happened, per the routing table,** never
  from participation alone, and never include the actor.
- **`fyi` posts never interrupt;** a mention in one is a `digest`-tier
  item.
- **Scheduled items are invisible to their recipient,** and are worked out
  again when the recipient's availability changes.
- **Availability is one resource with a limited view for others,** and
  agents have none of their own.
- **Away delegates are suggestions in the delivery summary,** never
  reassignments.
- **`incidents_interrupt` replaces `interrupt_for`,** on by default and
  opted out of by each member.
- **Email carries digests and incidents by default, and `today` batches
  for members who opt in;** `now` items are never emailed singly.
- **The inbox is private to its member,** admins included.
- **Updates inside atomic operations carry their ETag in
  `meta.if_match`.**
- **Digests list, briefs summarise.**

## Open questions

None. Resolved in review on 2026-10-09:

1. **Mentions in `fyi` posts** create a `mentioned` item at `digest` tier.
2. **`incidents_interrupt`** is on by default, and each member opts out.
3. **Email** carries digests and incidents by default, and `today` batches
   for members who opt in, on advice.
