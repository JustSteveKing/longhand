# RFC 0010: Events, webhooks and the stream

- **Status:** Accepted
- **Created:** 2026-10-09
- **Depends on:** RFC 0002, RFC 0003, ADR 0002, ADR 0012, ADR 0025, ADR 0036, ADR 0043

## Summary

Every change in Longhand emits an event in CloudEvents 1.0 format. The
same events reach webhooks, the real-time stream and the events endpoint,
so integrations, agents and open clients all see one shape. This RFC
defines the envelope, how events are recorded so that none is lost or
invented, who receives each one, webhook subscriptions and delivery, the
events endpoint, and the Server-Sent Events stream served by a Mercure
hub. It is section 12 of [the spec](../spec.md) and the stream part of
its Appendix A. The catalogue of event types is spread across the RFCs
that own them; this one collects the rules they share.

## Problem

The spec's design holds up: CloudEvents, Standard Webhooks, at-least-once
delivery, an events endpoint for recovery, and one-way SSE through
Mercure. What it leaves open, or gets wrong for what has been decided
since:

- **Visibility per recipient.** Several resources now look different to
  different people: limited availability (ADR 0036), private spaces to
  admins (ADR 0025), check-in answers before you answer (ADR 0043),
  drafts. An event carrying "the full resource" has to carry the
  recipient's version of it.
- **Agents and subscriptions.** RFC 0003 made `webhooks:write`
  human-only, while the spec lets agents create subscriptions and RFC
  0007 has a brief generator subscribe to `brief.requested`.
- **The stream and permissions.** A Mercure hub sends one update to
  everyone subscribed to a topic and cannot filter by event type per
  subscriber, so "filtered by its scopes" and "the recipient's version"
  both have to be designed into the topics.
- **Laravel's Mercure driver** wraps every payload in Echo's own envelope
  (`channels`, `event`, `payload`). That is right for Echo clients and is
  not the wire format the spec promises.
- **Shapes.** `/rotate-secret`, `/enable`, `/test` and `/retry` are action
  endpoints, the events endpoint's `?after=` is not JSON:API, and the
  envelope's `source` names a URL that does not exist.

## Goals

1. An event exists if and only if the change it describes was committed.
2. Every event reaches a recipient as that recipient is allowed to see
   it, or not at all.
3. A consumer never needs a follow-up fetch to act on an event.
4. A consumer that was offline can always catch up, by webhook retry, by
   stream replay, or from the events endpoint.
5. Nothing an agent receives exceeds its scopes and space allow-list.

## Non-goals

- Two-way real-time traffic. Every write goes through REST.
- Batched webhook deliveries.
- Published JSON Schemas for each event type, which wait for a real
  domain like problem types do (ADR 0002).
- Guaranteed ordering. Each event carries the full resource and its
  time, so consumers keep the newest state per subject.

## Design

### The envelope

Events are CloudEvents 1.0 in structured JSON mode:

```json
{
  "specversion": "1.0",
  "type": "longhand.request.completed",
  "source": "urn:longhand:workspace:wsp_01J8...",
  "id": "evt_01JAC3...",
  "time": "2026-10-09T10:14:00Z",
  "subject": "req_01JAA4...",
  "datacontenttype": "application/json",
  "dataschema": "https://api.longhand.example/schemas/v1/events/request.completed.json",
  "actor": "mem_01JA7R...",
  "actorkind": "human",
  "onbehalfof": null,
  "surface": "rest",
  "data": {
    "resource": {
      "type": "requests",
      "id": "req_01JAA4...",
      "attributes": { "state": "done", "...": "..." },
      "relationships": { "...": "..." }
    },
    "previous": { "state": "accepted" },
    "note": "Reviewed and merged"
  }
}
```

- **`type`** is `longhand.` plus the event name (ADR 0002).
- **`source`** is a URN naming the workspace. The spec's
  `/v1/workspaces/{id}` is not a URL in this API (RFC 0003), and
  `source` plus `id` must be unique, which the workspace URN gives.
- **`id`** is an `evt_` ULID, the same on every route an event takes.
  Consumers deduplicate on it.
- **`subject`** is the ID of the resource the event is about.
- **Extensions** `actor`, `actorkind` (ADR 0019's `kind`, not the spec's
  `actortype`), `onbehalfof` and `surface` say who did it, for whom and
  through what, so consumers can ignore their own writes and treat agent
  activity differently. CloudEvents limits extension names to lowercase
  letters and digits, which these are.
- **`data.resource`** is the full resource as a JSON:API resource object,
  as the recipient would see it (below). **`data.previous`** holds only
  the attributes that changed, with their old values. Anything else an
  event catalogues (`note`, `delivery`, `outcome` and so on) sits beside
  them in `data`.
- `dataschema` uses the documentation domain and is not resolvable until
  there is a real one.

### Recording events: the outbox

An event is written to an `events` outbox table in the same database
transaction as the change it describes. A rolled-back change leaves no
event, and a committed one always has its event. Queued workers read the
outbox and fan each event out to webhooks and the stream. The outbox is
also the store the events endpoint reads. How long events are kept is
the retention RFC's to decide; until it does, they are kept for 30 days.

Domain code records events; it never delivers them. Nothing outside the
outbox workers talks to a subscriber or the hub.

### Who receives what

Visibility is that of the member an event is delivered to, evaluated when
it is delivered, not when it happened:

- A member receives events about resources they can see, and only those.
- An agent receives events inside its space allow-list, and only of
  resource families its scopes can read: `threads:read` for threads,
  posts, requests and decisions; `spaces:read` for spaces and
  memberships; `members:read` for members and limited availability;
  `check_ins:read`, `briefs:read`, `inbox:read` likewise.
- `inbox.*` and `digest.sent` go only to the item's member, or an agent
  acting for them. `brief.requested` goes only to the brief's generator,
  and other brief events only to its reader or, for a roll-up, to those
  who can see the thread.
- **The resource is rendered for the recipient.** An admin outside a
  private space, a colleague reading availability, a respondent who has
  not answered a check-in yet: each gets the resource as they would from
  the API, with `meta.access` where it applies, or does not get the event
  at all when the resource would be `404` to them (ADR 0012). A member
  who has not yet answered a check-in receives no `post.created` for
  others' answers; once they can see them, clients refetch the thread.
- Muting a space (RFC 0004) affects the inbox, never events. Events are
  data, not notifications.

### Subscriptions

A webhook subscription is a `subscriptions` resource:

```json
{
  "type": "subscriptions",
  "id": "sub_01JAF0...",
  "attributes": {
    "url": "https://hooks.acme.example/longhand",
    "types": ["request.*", "thread.stale", "decision.recorded"],
    "filter": { "actorkind": ["human"] },
    "description": "Sync requests to Linear",
    "status": "active",
    "disabled_reason": null,
    "created_at": "2026-10-09T12:00:00Z"
  },
  "relationships": {
    "delivers_as": { "data": { "type": "members", "id": "mem_01JA8B..." } },
    "spaces": { "data": [{ "type": "spaces", "id": "spc_01JA9S..." }] },
    "created_by": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "deliveries": { "links": { "related": "/v1/subscriptions/sub_01JAF0.../deliveries" } }
  }
}
```

- **Humans create subscriptions,** with `webhooks:write`, which no agent
  can hold (RFC 0003). This follows Slack's app model: any non-guest
  member can wire up an integration, it only ever receives what its
  member could see, and owners and admins can require approval first.
- **Approval.** A subscription created by a member who is not an owner
  or admin starts `pending`, sends nothing, and gives every owner and
  admin a `subscription_pending` inbox item. An owner or admin approves
  it with a `PATCH` of `status` to `active`, as action
  `subscription.approve`, or deletes it with an optional `meta.note`;
  either way the creator gets a `subscription_reviewed` inbox item. The
  workspace's `subscription_approval` setting, on by default, can be
  turned off by an owner, after which members' subscriptions are active
  at once. Owners' and admins' own subscriptions never wait. A webhook
  sends workspace content to an address outside Longhand, which is why
  approval is the default.
- **`delivers_as`** is the member whose visibility the subscription
  receives: for a member, themselves or an agent they own; for an owner
  or admin, any member. This is how an agent is wired to events without
  holding `webhooks:write`: someone creates a subscription that delivers
  as the agent, for example the brief generator with
  `types: ["brief.requested"]`, and the agent receives exactly what it
  may see. Changing `delivers_as` is a human action like any other, and a
  member's change to it goes back through approval.
- **`types`** takes exact names and a trailing `*` per resource, without
  the `longhand.` prefix. **`spaces`** narrows to some spaces;
  **`filter.actorkind`** to human or agent activity. Filters only ever
  narrow what `delivers_as` may see.
- **`url`** must be `https`, must not resolve to a private, loopback or
  link-local address, and is checked again at every delivery, so a DNS
  change cannot point a subscription inside the network. Redirects are
  not followed.
- **The signing secret** is returned once, in the create response's
  `meta.secret`, as a Standard Webhooks `whsec_` secret. A new one is
  created with `POST /v1/subscriptions/{subscription}/secrets`, shown once;
  for 24 hours deliveries are signed with both.
- **`status`** is `pending`, `active` or `disabled`. A `PATCH` of
  `status` to `active` re-enables a disabled subscription, as action
  `subscription.enable`; `disabled` disables it by hand. Owners and
  admins can see, disable and delete every subscription in the
  workspace; members see and manage their own.

### Delivery

Each attempt to send an event to a subscription is recorded as a
`webhook_deliveries` resource (`dlv_`), with the event, status, HTTP
status, duration, the first kilobyte of the response body and when the
next attempt is due.

- **Signing** follows Standard Webhooks: `webhook-id` (the event `id`,
  the same on every retry), `webhook-timestamp`, and `webhook-signature`,
  HMAC-SHA256 over `{id}.{timestamp}.{body}`. During a secret rotation
  the header carries both signatures.
- **The body** is the CloudEvent, with `Content-Type:
  application/cloudevents+json`.
- **Success** is any `2xx` within 10 seconds.
- **At least once.** A failure retries with exponential backoff and
  jitter, roughly 5 seconds, 5 minutes, 30 minutes, 2 hours, 5 hours,
  then every 10 hours, for up to 3 days.
- **Disabling.** After 3 days of continuous failure the subscription is
  `disabled` with `disabled_reason: "failing"`, `subscription.disabled`
  fires, and its creator gets a `webhook_disabled` inbox item (RFC
  0006). Events during the outage stay in the events endpoint for the
  rest of their retention.
- **Visibility at delivery.** If `delivers_as` can no longer see the
  resource when an attempt is due, the delivery is `skipped`, not sent.
- **Replaying.** `POST /v1/webhook-deliveries` with a subscription and an
  event sends that event again, as a new delivery; with
  `"test": true` and no event, it sends a `subscription.test` event with a
  sample payload. Both are owners' and admins' actions.

### The events endpoint

`GET /v1/events` is the recovery path for missed webhooks and for
clients that were offline. It returns the caller's view of events from
the retention period, oldest first, as JSON:API resources whose `attributes`
hold the CloudEvent exactly as it would have been delivered:

```json
{
  "type": "events",
  "id": "evt_01JAC3...",
  "attributes": {
    "cloudevent": { "specversion": "1.0", "type": "longhand.request.completed", "...": "..." }
  }
}
```

- `filter[after]` takes an event ID and returns events after it, which is
  how a consumer resumes from the last event it processed. ULIDs sort by
  time, so this needs no cursor of its own; pages are still cursor
  paginated.
- Also `filter[type]` (with trailing `*`), `filter[space]`,
  `filter[subject]` and `filter[actorkind]`.
- Visibility is the caller's, at the time of the request.

### The stream

`GET /v1/stream` is a Server-Sent Events stream of the same events for
the caller, and the only real-time transport in v1. It is served by a
Mercure hub, the one built into FrankenPHP, routed under `/v1/stream` at
the proxy, so PHP never holds a connection open.

**Wire format.** Each message carries one CloudEvent as `data`, with the
CloudEvents `type` as the SSE `event`, so browsers can
`addEventListener` per type. The SSE `id` is the hub's cursor, opaque and
not the CloudEvent `id`; clients deduplicate on the `id` inside `data`.
The hub sends heartbeats and a `retry:` field.

**Publishing.** The outbox workers publish CloudEvents to the hub
directly through `symfony/mercure`'s `HubInterface`, setting the update's
`type` to the event type and its data to the rendered CloudEvent.
Laravel's `mercure` broadcast driver is not used for this stream, because
it wraps payloads in Echo's envelope; the web app's own use of
broadcasting is RFC 0012's.

**Topics.** A hub delivers one update to every subscriber authorised for
its topic, and cannot filter by type per subscriber. Permissions are
therefore built into the topics:

- `.../spaces/{space}/{family}` per space and resource family (`threads`,
  `posts`, `requests`, `decisions`, `check_ins`, `spaces`), only for
  spaces with `workspace` visibility, and only granted to non-guest
  members, for events that all of them see identically;
- `.../workspace/{family}` for workspace-wide events such as members and
  limited availability;
- `.../members/{member}` for everything that is only for one member, or
  that looks different to different members: inbox items, digests,
  briefs, drafts, an agent's view, a member's own full availability,
  check-in answers before everyone can see them, every event in a
  private or direct space, every event a guest receives, and
  `stream.revoked`.

The rule is simple: an event goes to a shared topic only if everyone
authorised for that topic would see it the same way; otherwise it is
rendered and published once per member who may see it, to their own
topic. A subscriber's grant lists exactly the topics their role, scopes
and allow-list permit, so an agent with no `check_ins:read` is never
granted a `check_ins` topic.

Private and direct spaces, and guests, always go through personal topics,
even though that means publishing an event once per member. Those are the
places where who may see something is the point, and memberships there
are small. It also means that losing access to them takes effect on the
next event, whatever the client does (below).

**Credentials.**

| Client | Credential |
| --- | --- |
| Server-side consumers and agents | A stream ticket, sent as `Authorization: Bearer` with the ticket's token |
| The first-party web app | A hub cookie (`mercureAuthorization`), `HttpOnly`, `Secure`, `SameSite=Strict`, scoped to the stream path, set by the web app |
| Third-party browser clients | A stream ticket in the URL |

A stream ticket is created with `POST /v1/stream-tickets`, a
`stream_tickets` resource (`stk_`) whose `token` attribute is a hub
subscriber token listing the caller's topics, and whose `stream_url`
carries it. A ticket only opens the stream, must be used within 60
seconds, and is recorded in the audit log. An open connection is not cut
when the 60 seconds pass.

**Revoking access.** When a member loses access to a space, is
deactivated, or has an agent's scopes or allow-list narrowed, Longhand
revokes their stream:

1. `stream.revoked` is published to their personal topic, with a
   `reason`. Every client must close the connection on receiving it and
   reconnect with a new credential. The web app and Longhand's own
   clients do so at once, so for them revocation is instant; if the
   removal was a mistake and has been undone, the new credential simply
   carries the access back.
2. New credentials reflect the change immediately: tickets are minted
   fresh with the current topics, and the web app's hub cookie lives for
   at most 10 minutes and is reissued on revocation.
3. For private spaces, direct spaces and guests, nothing more is needed:
   their events go to personal topics, and Longhand stops publishing to
   the removed member's topic the moment access ends, so even a client
   that ignores `stream.revoked` receives nothing further.
4. For `workspace` spaces, which every non-guest member may join anyway,
   a hostile client that ignores the event could keep its old grant until
   the hub closes the connection. The hub closes every connection after
   10 minutes, and a reconnect needs a current credential, so this is the
   only remaining window, and only for content any member of the
   workspace could have opened.

A member who gains access gets `stream.revoked` too, with
`reason: "access_changed"`, so their client reconnects with the new
topics.

**Resume.** A client reconnects with the last SSE `id` it received, as
`Last-Event-ID` or `?last_event_id=`, and the hub replays from there out
of its history, kept for at least an hour. The response's `Last-Event-ID`
header names the cursor it actually resumed from; if that differs from
what was sent, events were lost, and the client catches up through
`GET /v1/events?filter[after]=` from the last CloudEvent it processed. A
`401` means the credential expired.

**Filtering.** `?spaces=` and `?types=` narrow the topics subscribed to,
and can never widen them.

The stream carries no delivery semantics of its own. A client raises a
notification only for `inbox.item_delivered`.

### Actions

| Action | Scope | Who |
| --- | --- | --- |
| `subscription.create` | `webhooks:write` | Any non-guest member; pending unless approval is off or they are an owner or admin |
| `subscription.update`, `subscription.enable`, `subscription.disable`, `subscription.delete`, `subscription.rotate_secret` | `webhooks:write` | The creator, owner, admin |
| `subscription.approve` | `webhooks:write` | Owner, admin |
| `webhook_delivery.replay`, `webhook_delivery.test` | `webhooks:write` | The creator, owner, admin |
| `stream.ticket` | Any scope | Any member |

Reading subscriptions and deliveries needs `webhooks:write`, since they
show URLs and response bodies; members read only their own. Reading events needs the scope for each
event's resource family.

### Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` / `POST` | `/v1/subscriptions` | List or create subscriptions |
| `GET` / `PATCH` / `DELETE` | `/v1/subscriptions/{subscription}` | Read, change, enable, disable, delete |
| `POST` | `/v1/subscriptions/{subscription}/secrets` | Rotate the signing secret |
| `GET` | `/v1/subscriptions/{subscription}/deliveries` | Recent deliveries |
| `POST` | `/v1/webhook-deliveries` | Replay an event, or send a test |
| `GET` | `/v1/webhook-deliveries/{delivery}` | One delivery |
| `GET` | `/v1/events` | Events still within retention |
| `POST` | `/v1/stream-tickets` | A stream ticket |
| `GET` | `/v1/stream` | The stream, served by the hub |

### Errors and identifiers

No new error codes. An unsafe or non-`https` URL is `422`
`validation-failed` on `/data/attributes/url`; replaying to a disabled
subscription is `409` `invalid-transition`.

`sub_`, `dlv_`, `evt_` and `stk_` are already in RFC 0002's table.

### Events

From the spec's catalogue: `subscription.disabled`, `subscription.test`
and `stream.reauthenticate`, renamed `stream.revoked` and sent on the
stream only, never to webhooks. Plus:

| Event | Fires when |
| --- | --- |
| `subscription.created` | A subscription is created, pending or active |
| `subscription.approved` | A pending subscription is approved |
| `subscription.enabled` | A disabled subscription is enabled again |
| `subscription.secret_rotated` | A new signing secret is issued |

Subscription events go only to owners, admins and the subscription's
creator.

## Alternatives considered

- **Thin events, an ID and a type only.** Smaller, and every consumer
  makes a fetch per event, at a time when what it fetches may have moved
  on.
- **Rendering each event once, for nobody in particular.** Simpler, and
  either over-shares or forces every resource to its most limited view.
- **Laravel's `mercure` broadcast driver for the public stream.** Less
  code, and an Echo-shaped wire format instead of CloudEvents.
- **WebSockets.** Two-way, which nothing needs, and harder to proxy,
  resume and authenticate than SSE.
- **Owners and admins only.** Simplest, and no personal integrations,
  which is where most integrations start.
- **Members' subscriptions active at once.** Slack's default for apps,
  and every member a possible path for workspace content to leave. It
  stays available as a setting.
- **Letting agents hold `webhooks:write`.** An agent could then point a
  subscription at itself with a wider `delivers_as`, or at anywhere.
- **Publishing after commit without an outbox.** A crash between the
  commit and the publish loses the event.

## Decisions this records

- **Events are recorded in an outbox in the same transaction** as their
  change, and only outbox workers deliver them.
- **Every event is rendered for its recipient,** with their visibility at
  delivery time, or not delivered.
- **Subscriptions deliver as a member,** human or agent, and only humans
  create them.
- **Any non-guest member can create a subscription,** for themselves or
  an agent they own, approved by an owner or admin unless the workspace
  turns approval off.
- **The stream publishes CloudEvents to Mercure directly,** not through
  Laravel's Echo-shaped driver.
- **Stream topics encode permissions:** shared topics only for events
  everyone authorised sees identically, personal topics otherwise.
- **`stream.revoked` forces a reconnect** whenever a member's access
  changes, and every client must obey it.
- **Private spaces, direct spaces and guests always use personal
  topics,** so losing access to them is immediate whatever the client
  does.
- **Hub connections last at most 10 minutes,** bounding the one remaining
  window, for `workspace` spaces only.
- **Events are kept as long as the retention RFC says,** 30 days until
  then.
- **The envelope's `source` is a workspace URN,** and `actortype` is
  `actorkind`.
- **Webhook URLs are checked against private addresses at every
  delivery.**

## Open questions

None. Resolved in review on 2026-10-09:

1. **Subscriptions** follow Slack's model: any non-guest member can
   create one that delivers as themselves or their agent, with owner or
   admin approval on by default.
2. **Revocation** is a `stream.revoked` event every client must obey,
   with private spaces, direct spaces and guests on personal topics so
   that it is immediate for them regardless.
3. **Event retention** follows the retention RFC.
