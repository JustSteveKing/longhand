# RFC 0001: Longhand v1

- **Status:** Accepted
- **Created:** 2026-10-08
- **Depends on:** ADR 0001
- **Amended by:** RFC 0002, RFC 0013

## Summary

Longhand is an async-first communication platform for remote teams spread
across timezones, and for the AI agents that work alongside them. It
models work and attention rather than a message stream: conversations
have a purpose, an owner and an end; intent is structured data; delivery
follows the recipient's working hours; agents are members under the same
rules as people. It is specified from [the spec](../spec.md). This RFC
sets the scope of v1, the principles every later RFC is held to, and how
the spec is split into the RFCs that follow.

## Problem

Stream-based chat breaks down for async teams because the stream is the
product. Unread counts and presence dots reward whoever happens to be
online. Decisions sink into scrollback. Requests have no owner and no
deadline, so "can someone look at this?" gets either everyone or nobody.
A teammate eight hours ahead wakes up to a wall of messages and has to
read all of it to find the two that needed them.

Agents make it worse. They can produce more messages than any person can
read, and in most tools an agent's message looks like anyone else's, with
no record of who is accountable for it.

## Goals

1. **Conversations end.** Every thread has a title, a purpose, one owner
   and a status, and resolving it records an outcome.
2. **Intent is data.** A post says whether it is an FYI, a question, a
   request, an update or a decision, and that field drives routing,
   delivery and summaries.
3. **Attention is protected.** The sender picks an urgency, the recipient
   decides what may interrupt them, and the server enforces the
   recipient's rules. The inbox holds only what needs a person to act,
   with a reason attached.
4. **Agents are members.** One identity, scope and audit model for people
   and agents. Agents are always labelled, draft by default, and never
   publish a decision.
5. **Generated content cites its sources.** A brief item with no citation
   fails validation, and every citation must be something the reader can
   already see.
6. **One domain, three surfaces.** REST, MCP and webhooks share the same
   resources, scopes and event types. Nothing is possible through one
   surface that the permission model forbids through another.
7. **Standard plumbing.** URL versioning, JSON:API with its error
   objects (RFC 0002 replaced RFC 9457 problem details),
   idempotency keys, cursor pagination, CloudEvents, Standard Webhooks
   signing, and the MCP authorisation spec. Nothing invented where a
   standard exists.
8. **Anyone can get in.** A person can create a workspace, invite others
   and join one they were invited to, through the product itself.

## Non-goals

Out of scope for v1, each addable later without changing the core
resources:

- Voice and video calls.
- File storage beyond attachments on posts.
- Federation between workspaces, and guests who belong to another
  workspace.
- Importing from Slack or any other tool.
- End-to-end encrypted spaces.
- A real-time two-way channel. The only real-time transport is a one-way
  event stream; every write goes through REST.

## Design

### The product in one paragraph

A person signs up and creates a **workspace**, or joins one through an
invitation. A workspace has **members**, human or agent. Work happens in
**spaces** (a team, a project, or a private group of up to eight), which
hold **threads**, which hold **posts**. There is no way to post into a
space without a thread. A post with the `request` intent creates a
**request** with one assignee and a due date; a post with the `decision`
intent, or resolving a decision thread, creates an immutable **decision**.
Each member publishes **availability**, and the server works out when
each post, request or mention is delivered to them and puts what needs
action in their **inbox**. A member can ask for a **brief**, a generated
catch-up in which every line cites its source. **Check-ins** replace
standups with recurring prompts answered in each person's own hours.

### Resources

| Resource | Replaces | Purpose |
| --- | --- | --- |
| `Member` | User and bot accounts | Human or agent, with timezone, working hours and response expectations |
| `Space` | Channel and DM | A team, project or private group. Holds threads, never loose messages |
| `Thread` | Channel scroll and reply threads | Titled conversation with an owner and a status |
| `Post` | Message | Content with an `intent` |
| `Request` | "Can someone...?" | An ask with one assignee, a due date and a state |
| `Decision` | Pinned messages | Immutable record of what was decided, by whom, and why |
| `InboxItem` | Unread badges and mentions | Something that needs a specific member to act |
| `Availability` | Presence dot | When a member works and what may interrupt them |
| `Brief` | Scrolling back | Generated catch-up with citations |
| `CheckIn` | Standups | Recurring async prompts with collected answers |
| `Subscription` | Event API | A webhook destination for events |

### Surfaces

- **The REST API** at `/v1`, the full contract.
- **The MCP server**, the same domain as tools, resources and prompts, for
  agents. It cannot perform administrative actions.
- **Webhooks and the event stream**, the same CloudEvents delivered to
  integrations, to open clients over Server-Sent Events, and through an
  events endpoint for recovery.
- **The first-party web app**, the React client from the Laravel starter
  kit, served through Inertia. Its controllers call the same Actions as the
  REST API and the MCP server, so the web app cannot do anything the domain
  forbids, and it does not go through the public API's OAuth tokens. It is
  a fourth caller of the domain, not a client of the API.

### Bounded contexts

The reference implementation splits the domain into eight contexts that
talk through domain events and each other's public Actions, never through
each other's models.

| Context | Owns |
| --- | --- |
| `Identity` | Sign-up, workspaces, invitations, members, agents, scopes, delegation, audit |
| `Conversations` | Spaces, threads, posts |
| `Commitments` | Requests, decisions |
| `Attention` | Availability, delivery, inbox, digests |
| `Briefs` | Generation, citations, feedback |
| `CheckIns` | Schedules, runs, responses |
| `Search` | Search projections, keyword and semantic search, embeddings (added by RFC 0013) |
| `Integration` | Outbox, CloudEvents mapping, webhooks, stream publishing |

### Names, until there is a domain

The spec used placeholders (`Async`, `api.example.dev`, `dev.example.`,
`async://`). Longhand has no domain yet, so v1 uses names that do not
depend on one, and the documentation uses the reserved `.example` domain:

| Placeholder in the spec | Longhand |
| --- | --- |
| Root namespace `Async\` | `Longhand\` |
| `https://api.example.dev/v1` | `https://api.longhand.example/v1` in documentation |
| `https://mcp.example.dev/v1` | `https://mcp.longhand.example/v1` in documentation |
| `https://api.example.dev/problems/{slug}` | `https://api.longhand.example/problems/{slug}` in documentation |
| Event type prefix `dev.example.` | `longhand.`, e.g. `longhand.request.completed` |
| MCP resource scheme `async://` | `longhand://` |

Problem `type` URLs are meant to be permanent, so product-specific problem
types cannot be published against a placeholder domain. They wait for a
real domain before the API is public.

The event prefix is the other lasting choice, and it is kept even after a
domain is bought. CloudEvents recommends a reverse-DNS prefix, and
`longhand.` is not one, but an event `type` is part of every consumer's
code, so changing it later would be a breaking change for all of them.
`longhand.` is short, unambiguous within the product, and does not tie the
contract to a domain that might change.

### How the spec is split

Each section of the spec becomes an RFC. Numbers are assigned as each is
written; this is the intended order.

| RFC | Covers | Spec sections |
| --- | --- | --- |
| 0002 | API conventions and errors | 2 |
| 0003 | Identity: onboarding, members, agents and permissions | 4, audit; onboarding is new |
| 0004 | Spaces, threads and posts | 5, 6 |
| 0005 | Requests and decisions | 7 |
| 0006 | The attention model | 8 |
| 0007 | Briefs | 9 |
| 0008 | Check-ins | 10 |
| 0009 | Search | 11 |
| 0010 | Events, webhooks and the stream | 12 |
| 0011 | The MCP server | 13 |
| 0012 | The first-party web app | none yet: Inertia controllers sharing the domain's Actions |
| 0013 | Reference implementation architecture | Appendices A and B |

The spec's endpoint reference (section 14) is not an RFC. It becomes the
OpenAPI document, written from the accepted RFCs. The spec's "Decided in
this draft" table becomes the first batch of ADRs, each extracted from the
RFC it belongs to.

The spec's open questions each become their own RFC when they are taken
up: retention and legal hold, encryption against AI features, who pays for
generation, importing from Slack, cross-workspace guests, and answer
detection.

## Alternatives considered

- **Build a chat app and add async features on top.** It is what most
  tools do, and it is the problem: once the stream is the product, every
  feature competes with it for attention. Longhand has a stream only as a
  transport.
- **Treat agents as integrations rather than members.** Simpler at first,
  but it gives agents a second permission model, and a second model is
  where an agent ends up able to do something a person with the same role
  could not.
- **Specify the whole product as one document.** That is the spec as it
  stands. It is the right way to see the whole, and the wrong way to
  review, build or teach it, because no single decision can be found,
  cited or superseded on its own.

## Decisions this records

- **The product is named Longhand,** with the names in "Names, until there
  is a domain" until one is bought, and the `longhand.` event prefix kept
  after that.
- **Onboarding is in v1,** owned by the Identity context: sign-up,
  creating a workspace, and joining one by invitation.
- **The web app is an Inertia client of the domain, not of the API.** Its
  controllers call the same Actions as REST and MCP.
- **v1 scope is the resources, surfaces and non-goals above.**
- **The domain is split into the bounded contexts above,** eight since
  RFC 0013 added Search.
- **Each section of the spec becomes an RFC, and the endpoint reference
  becomes the OpenAPI document.**

The decisions inside each part (prefixed ULIDs, JSON:API errors, SSE through
Mercure and so on) are recorded as ADRs from the RFC that covers them, not
from this one.

## Open questions

None. Resolved in review on 2026-10-08:

1. **Onboarding** is part of v1, under Identity (RFC 0003).
2. **The web app** uses Inertia controllers that share the domain's
   Actions, rather than calling the public API.
3. **RFCs describe the product.** Teaching material lives outside them.
4. **The `longhand.` event prefix** is accepted, and kept once a domain
   exists.
