# RFC 0008: Check-ins

- **Status:** Accepted
- **Created:** 2026-10-09
- **Depends on:** RFC 0004, RFC 0006, RFC 0007, ADR 0032, ADR 0040

## Summary

A check-in is an async standup. Instead of a meeting at a time that
suits one timezone, each person gets the same short set of questions at
the start of their own working day, answers when they can, and the team
reads everyone's answers in one thread. Blockers go straight to someone
who can unblock them, and when the check-in closes the thread is rolled
up so anyone can catch up in one read. This RFC defines check-ins, their
runs, responses, the standup template, and how a run moves from prompt
to summary. It is section 10 of [the spec](../spec.md).

## Problem

A synchronous standup costs the whole team a meeting every day and
always lands badly for someone: before coffee in one timezone, at the
end of the day in another, during someone's focus time. Status meetings
are worse. Most of what is said is for the record, and the one thing that
matters, a blocker, waits until the meeting to be said and is forgotten
afterwards.

The spec's check-ins have the right shape, and leave several things
open: who gets a blocker, what the run's thread looks like once there
are a dozen answers in it, when that thread ends, what happens to people
who are away or have nothing to say, and how one run can serve a team
spread across timezones. Its schedule is a cron string, which RFC 0006
already ruled out for people, and its response endpoint is not JSON:API.

## Goals

1. Everyone is asked at the start of their own working day, never at a
   time chosen for someone else.
2. Each person's answers read as one standup update, not a scatter of
   posts.
3. A blocker reaches someone who can act on it the moment it is
   answered, not when someone reads the thread.
4. Nobody is chased for a check-in while they are away, and saying
   "nothing today" is a first-class answer.
5. Every run ends: it closes, it is rolled up, and its thread resolves
   when the next run starts.
6. Agents can take part, answering from real data, labelled as agents
   like everything else.

## Non-goals

- Participation scores, streaks or per-person response rates over time.
  A check-in is a way to share, not a way to monitor.
- Free-form surveys and polls.
- Rich answers: each answer is Markdown, like any post.
- More than one reminder per person per run.

## Design

### Check-ins

```json
{
  "type": "check_ins",
  "id": "chk_01JAE1...",
  "attributes": {
    "name": "Billing standup",
    "status": "active",
    "schedule": { "days": ["mon", "tue", "wed", "thu", "fri"], "time": "09:30" },
    "timing": "respondent",
    "timezone": null,
    "respondents": "space",
    "questions": [
      { "key": "done", "prompt": "What did you get done since the last check-in?", "kind": "update" },
      { "key": "next", "prompt": "What are you working on next?", "kind": "update" },
      { "key": "blockers", "prompt": "Anything blocking you?", "kind": "blocker", "optional": true }
    ],
    "close_after": "PT12H",
    "roll_up": true,
    "created_at": "2026-10-09T10:00:00Z"
  },
  "relationships": {
    "space": { "data": { "type": "spaces", "id": "spc_01JA9S..." } },
    "owner": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "named_respondents": { "data": [] },
    "runs": { "links": { "related": "/v1/check-ins/chk_01JAE1.../runs" } }
  }
}
```

- **`schedule`** is the days and a local time, as for digests (RFC 0006).
- **`timing`** is `respondent` or `space`. With `respondent`, each person
  is asked at `schedule.time` in their own timezone, so a team across
  eight hours is each asked at the start of their morning. With `space`,
  everyone is asked at one moment, `schedule.time` in `timezone`, for
  check-ins where the shared moment matters more than the local hour.
- **`respondents`** is `space`, meaning every human member of the space
  when each run opens, or `named`, meaning the `named_respondents`, who
  must be members of the space and can include agents. A standup for "the
  team" stays right as people join and leave.
- **`questions`** are one to six, each with a `key`, a `prompt` of up to
  200 characters, a `kind`, and whether it is `optional`. `kind` is
  `update`, for something to share, or `blocker`, for something that
  needs someone else.
- **`close_after`** is how long each person has, from when they are
  asked: at least `PT1H`, at most `P7D`.
- **`roll_up`** rolls up each run's thread when it closes (RFC 0007).
- **The owner** is one human member of the space, the creator unless
  they name someone else. Blockers go to them, and they answer for the
  check-in, like a thread's owner.
- **`status`** is `active`, `paused` or `archived`. A paused check-in
  opens no runs; an archived one is read-only. Check-ins are never
  deleted, so their runs and threads stay where they are.

Changes to a check-in apply from its next run.

### The standup template

Creating a check-in with `template: "standup"` fills in everything the
caller does not send: weekdays at 09:30, `timing: "respondent"`, every
member of the space, the three questions above, twelve hours to answer,
and a roll-up at the end. A team gets a working async standup from a name
and a space, and changes what does not fit.

```json
{
  "data": {
    "type": "check_ins",
    "attributes": { "name": "Billing standup", "template": "standup" },
    "relationships": { "space": { "data": { "type": "spaces", "id": "spc_01JA9S..." } } }
  }
}
```

`template` is only read on create and is not stored.

### Runs

Each scheduled day produces one run, a `check_in_runs` resource:

```json
{
  "type": "check_in_runs",
  "id": "run_01JAE5...",
  "attributes": {
    "status": "open",
    "opens_at": "2026-10-13T06:30:00Z",
    "closes_at": "2026-10-14T05:30:00Z",
    "counts": { "asked": 6, "responded": 4, "nothing_to_report": 1, "excused": 1, "late": 0 },
    "created_at": "2026-10-13T06:30:00Z"
  },
  "relationships": {
    "check_in": { "data": { "type": "check_ins", "id": "chk_01JAE1..." } },
    "thread": { "data": { "type": "threads", "id": "thr_01JAE6..." } },
    "summary": { "data": null },
    "responses": { "links": { "related": "/v1/check-in-runs/run_01JAE5.../responses" } }
  }
}
```

**Opening.** A run opens at the earliest moment anyone is asked: with
`respondent` timing, the earliest of the respondents' local scheduled
times that day. Opening it:

- creates the run's thread in the check-in's space, titled with the
  check-in's name and the date, with `purpose: "discussion"`, owned by the
  check-in's owner, with every respondent as a participant;
- works out who is asked: the respondents at that moment, less anyone
  who is away for the whole of their answering window (RFC 0006), who is
  **excused** and asked nothing;
- gives each person asked a `check_in_due` inbox item, delivered at
  `schedule.time` on that day in their timezone. If that is outside their
  working hours or in a focus block, it arrives when the next working
  window starts, worked out by the same function as any delivery (ADR
  0032). A check-in never interrupts.

The thread is visible to the space as soon as the run opens, but each
person's prompt arrives in their own morning, never earlier.

**One reminder.** Anyone asked who has not answered, said there is
nothing to report, or been excused gets one reminder, two working hours
before their window closes: their `check_in_due` item is delivered again,
reopening it if they had dismissed it, and `inbox.item_delivered` fires.
If that moment falls outside their working hours, the reminder comes at
the last working moment before it; if their whole window is outside
working hours, or shorter than four hours, it comes halfway through the
window. Nobody gets a second one.

**Closing.** Each person's window is `close_after` from when their prompt
was delivered, so someone asked later is not given less time. The run
closes when the last window ends. On closing:

- open `check_in_due` items close, with `done_cause: "run_closed"`;
- if `roll_up` is on, the thread is rolled up (RFC 0007), requested by the
  check-in's owner but not counted against their daily brief limit, since
  the check-in asked for it rather than a person, and the run's `summary` points at it;
- `check_in.closed` fires, with the counts and the summary.

**Ending.** The run's thread stays open after the run closes, so blockers
and answers can be discussed. It resolves itself when the next run opens,
with an outcome naming the run's counts, so a daily standup always has
exactly one live thread. If it has open requests then, it stays open and
its owner gets the usual inbox items; nothing is resolved over an
unfinished commitment.

**Running one now.** The owner can open an extra run at any time with
`POST /v1/check-in-runs`, for a standup on a day that is not scheduled or
a one-off status round. It behaves like a scheduled run, opening
immediately.

### Responses

A person answers a run once, with a `check_in_responses` resource:

```json
{
  "data": {
    "type": "check_in_responses",
    "attributes": {
      "answers": [
        { "key": "done", "text": "Shipped the retry backoff for webhooks." },
        { "key": "next", "text": "Idempotency keys on the billing endpoints." },
        { "key": "blockers", "text": "Need a staging Stripe key, @priya can you sort one?" }
      ]
    },
    "relationships": {
      "run": { "data": { "type": "check_in_runs", "id": "run_01JAE5..." } }
    }
  }
}
```

Submitted with `POST /v1/check-in-responses`, as action
`check_in.respond`. Every required question needs an answer, and an
answer's `key` must be one of the run's questions. Longhand then writes
the answers into the run's thread as the respondent:

- **One `update` post** holding every `update` answer, each under its
  question as a heading. A person's standup reads as one post, the way it
  would have been said in the meeting.
- **One `question` post per `blocker` answer,** replying to that update.
  A blocker is a question by intent, so it is routed like one (RFC 0006):
  to anyone it mentions, and otherwise to the thread's owner, who is the
  check-in's owner. It is in someone's inbox the moment it is posted.
  The respondent marks it answered when it is resolved (ADR 0027).

The response records which posts it created, in its `posts` relationship.
Changing an answer afterwards is editing those posts (RFC 0004).

**Nothing to report.** A person can answer with
`"nothing_to_report": true` and no answers. That posts nothing, closes
their inbox item, and counts as `nothing_to_report` rather than as a gap.
On quiet days, this is the most useful answer there is.

**Answer first.** Until a person asked in a run has answered, said
there is nothing to report, or reached the end of their window, the run's
thread hides everyone else's answers from them, and the replies to
those answers. They see the thread's title and counts, and nothing that
would shape what they write. Anything that needs them is the exception: a
post that mentions them, or a blocker routed to them as the check-in's
owner, is visible at once, because a blocker should not wait for someone
else's standup. Hidden posts are `404` to them (ADR 0012), and are left
out of their briefs' source bundles until they can see them. People in
the space who were not asked, and anyone excused, see everything as it
arrives.

**Late answers.** After someone's window has ended, they can still
answer while the run's thread is open; the response is marked `late` and
counted. The roll-up is not redone; anyone can roll the thread up again.

**Agents as respondents.** An agent named as a respondent gets
`check_in.run_opened` and a `check_in_due` item at once, since agents
have no hours (ADR 0036), and answers the same way, typically from real
data: what was deployed, what failed, what is queued. Its posts follow
its own scopes, so an agent with only `posts:write:draft` produces drafts
that a human publishes, and it is labelled an agent like everywhere else.

A member who is not asked in a run cannot respond to it: `403`
`not-a-respondent`. A second response from the same person is `409`
`resource-conflict`; they edit their posts instead.

### Who can do what

| Action | Scope | Who |
| --- | --- | --- |
| `check_in.create` | `check_ins:write` | Any non-guest member of the space |
| `check_in.update`, `check_in.pause`, `check_in.archive` | `check_ins:write` | The check-in's owner, the space's owner, admin |
| `check_in.run_now` | `check_ins:write` | The check-in's owner |
| `check_in.respond` | `check_ins:write` | Anyone asked in the run |

Pausing and archiving are `PATCH`es of `status`. Anyone who can see the
space can read its check-ins, runs and responses, with `check_ins:read`.

### Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/v1/spaces/{space}/check-ins` | A space's check-ins |
| `POST` | `/v1/check-ins` | Create a check-in, optionally from the standup template |
| `GET` / `PATCH` | `/v1/check-ins/{check_in}` | Read, change, pause, archive |
| `GET` | `/v1/check-ins/{check_in}/runs` | Its runs, newest first |
| `POST` | `/v1/check-in-runs` | Open a run now |
| `GET` | `/v1/check-in-runs/{run}` | A run, with its counts |
| `GET` | `/v1/check-in-runs/{run}/responses` | Who has answered |
| `POST` | `/v1/check-in-responses` | Answer, or say there is nothing to report |
| `GET` | `/v1/check-in-responses/{response}` | One response |

Responses are never edited or deleted through their own resource, so they
take no `If-Match`.

### Errors and identifiers

One new product-specific error, added to RFC 0002's index:

| `code` | Status | When | `meta` |
| --- | --- | --- | --- |
| `not-a-respondent` | 403 | Responding to a run the caller was not asked in | `run` |

Responding after the run's thread has resolved is `409`
`thread-not-open` (RFC 0004).

One new prefix, added to RFC 0002's table: `rsp_` for
`check_in_responses`. `chk_` and `run_` are already there.

### Events

From the spec's catalogue: `check_in.run_opened` (with the thread),
`check_in.response_submitted` (with the respondent) and `check_in.closed`
(with the counts and `summary_brief`). Plus:

| Event | Fires when |
| --- | --- |
| `check_in.created` | A check-in is created |
| `check_in.updated` | Its schedule, questions, respondents or status change |

## Alternatives considered

- **One post per answer.** The spec's shape, and a dozen people's answers
  become forty posts with nothing holding each person's update together.
- **Blockers as ordinary answers.** They wait in the thread until someone
  reads it, which is the meeting's problem again.
- **One shared time for everyone by default.** Easier to reason about,
  and someone is always asked at night.
- **Leaving run threads open.** A daily standup would leave a trail of
  open threads, each going stale.
- **Showing everyone's answers from the start.** Helps people coordinate,
  and the first answer sets the tone for the rest; standups are more
  honest when people write their own first.
- **Repeated reminders.** One is a nudge; more is the chasing a check-in
  exists to replace.
- **Counting silence and "nothing to report" the same.** One is a gap and
  the other is an answer, and a team should be able to tell them apart.

## Decisions this records

- **Check-ins ask each person at their own local time by default,**
  through the same delivery function as everything else, and never
  interrupt.
- **A response is one `update` post, with each blocker as a `question`
  reply,** routed to whoever it mentions, or the check-in's owner.
- **People who are away for the whole window are excused,** and "nothing
  to report" is an answer.
- **Each person gets the full window,** and the run closes when the last
  window ends.
- **A run's thread resolves when the next run opens,** unless it has open
  requests.
- **One reminder, two working hours before each person's window closes.**
- **Answer first:** a person asked in a run sees others' answers only
  once they have answered, except posts that need them.
- **Check-in roll-ups do not count against anyone's daily brief limit.**
- **`respondents: "space"` follows the space's membership** at each run.
- **The standup template** gives a working async standup from a name and
  a space.

## Open questions

None. Resolved in review on 2026-10-09:

1. **Reminders:** one, before each person's window closes.
2. **Others' answers** are hidden until the person has answered.
3. **Check-in roll-ups** are exempt from the daily brief limit.
