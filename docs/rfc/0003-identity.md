# RFC 0003: Identity

- **Status:** Accepted
- **Created:** 2026-10-08
- **Depends on:** RFC 0001, RFC 0002, ADR 0003, ADR 0013
- **Amended by:** RFC 0007, RFC 0009, RFC 0010, RFC 0011, RFC 0012

## Summary

Identity covers how people get into Longhand and who can do what once
they are in: signing up, creating a workspace, inviting and joining,
members and their roles, agents and the rules they work under, delegation,
how every caller authenticates, the scopes that limit tokens, and the
audit log that records all of it. People and agents are both members of
a workspace, under one permission model. This RFC is section 4 of
[the spec](../spec.md), plus onboarding, which the spec did not cover and
RFC 0001 put in v1.

## Problem

The spec defines members and agents well, but nothing before them: there
is no way to create a workspace, invite a colleague or accept an
invitation, though its own `member.joined` event mentions one. Tokens are
bound to a workspace, so "who is this caller" depends on how the caller
joined in the first place.

The spec's scopes also have gaps. There is no read scope for briefs or
check-ins, so the read-only `get_brief` MCP tool needs `briefs:write`. The
example agent lists `posts:publish` in its approval rules, which is not a
scope. Reassigning a request is open to "assignee or requester" while a
separate `requests:assign` scope exists. And ADR 0013 makes state changes
`PATCH`es, so one route now performs several actions, and scopes can no
longer be checked per route.

Agents raise the stakes. An agent with a broad token and no owner, or one
that can widen its own access, turns a helpful tool into an unaccountable
one.

## Goals

1. A person can sign up, create a workspace, invite others and join a
   workspace they were invited to, entirely through the web app.
2. People and agents are members under one permission model, and nothing
   an agent writes can be passed off as a person's.
3. Every agent has a human owner who is accountable for it, an explicit
   allow-list of spaces, and scopes it cannot widen itself.
4. Every action is checked against the caller's role, the token's scopes
   and what the caller can see, whichever surface it arrives through.
5. Every state change is in an append-only audit log that names the
   actor, the actor's kind, any principal they acted for, the scope used
   and the surface.

## Non-goals

- Any sign-in other than email and password or a passkey: magic links,
  social login, single sign-on and SCIM provisioning. Passkeys and
  optional two-factor authentication were added by RFC 0012.
- Guests from another workspace (RFC 0001 non-goal).
- Billing and plans.
- What happens to a member's content when they leave, which belongs to
  the retention RFC.

## Design

### Accounts, workspaces and members

Three things, kept separate:

- An **account** is a person's login: an email address, a password and a
  verified email. It belongs to no workspace. Accounts are part of the web
  app and do not appear in the API.
- A **workspace** is a team using Longhand, with its own members, spaces
  and settings.
- A **member** is an account in a workspace, or an agent. A person who
  belongs to three workspaces has one account and three members, each
  with its own role, handle, timezone and availability.

Everything in the API is a member, never an account. A token belongs to
one member, and so to one workspace (RFC 0002), which is why workspace IDs
never appear in URLs.

### Onboarding

All of onboarding happens in the web app, through Inertia controllers that
call the Identity context's Actions (ADR 0003).

**Signing up.** A person creates an account with an email address and a
password, and verifies the email before they can create or join a
workspace. This is the starter kit's own registration, kept as it is.

**Creating a workspace.** A verified account creates a workspace with a
name and a handle. The account becomes the workspace's first member, with
the `owner` role. The new member's timezone is taken from the browser and
can be changed. A workspace starts with one space, `General`, of kind
`team`, so the first thread has somewhere to go.

**Inviting.** An owner or admin invites by email address, choosing the
role the invitation grants and, for a guest, the spaces they are added to.

- An invitation is valid for 7 days, and can be revoked or resent until
  it is accepted.
- Inviting an address that is already a member, or that has a pending
  invitation, is `409` `resource-conflict`.
- Admins can invite as `admin`, `member` or `guest`. Only owners can
  invite as `owner`.

**Joining.** The invitation link opens the web app. A person who is
signed in accepts with the account they are using, whether or not its
email matches the invited address, so a work address can be accepted from
a personal account. Someone who is not signed in signs up or signs in
first. Accepting creates the member, adds them to any spaces the
invitation named, and emits `member.joined`. An expired or revoked
invitation shows a page saying so and who to ask.

**Joining by email domain.** An owner can list email domains whose
people may join without an invitation. To add a domain, the owner must
have a verified address at it themselves, and public mail providers such
as `gmail.com` are refused. Anyone whose account has a verified address
at a listed domain sees the workspace in the web app and can join it as a
`member`, never in any higher role. Joining this way emits
`member.joined`, and the audit log records that it came through the
domain rather than an invitation. Removing a domain stops new people
joining through it and leaves existing members alone.

**Belonging to several workspaces.** The web app has a workspace switcher.
Each workspace keeps its own session context, and signing out signs out
of all of them.

### Roles

Every human member has one role. Agents have no role; their rights come
from their scopes (below).

| Role | Can |
| --- | --- |
| `owner` | Everything an admin can, plus invite or promote owners, change the workspace handle, and delete the workspace |
| `admin` | Manage members and invitations, manage any agent, create and archive any space, manage webhook subscriptions, read the audit log |
| `member` | Create spaces, create and manage agents they own, join any space with `workspace` visibility, create webhook subscriptions for themselves or their agents, subject to approval (RFC 0010) |
| `guest` | See only the spaces they were added to. Cannot create spaces, invite, or own agents |

A workspace always has at least one owner. Removing, demoting or
deactivating the last owner is `409` `last-owner`.

### Members

A member is a JSON:API resource of type `members`:

```json
{
  "type": "members",
  "id": "mem_01JA7Q...",
  "attributes": {
    "kind": "human",
    "display_name": "Steve McDougall",
    "handle": "steve",
    "role": "admin",
    "status": "active",
    "timezone": "Europe/London",
    "availability": { "in_window": false, "next_window_starts_at": "2026-10-09T08:30:00Z" },
    "created_at": "2026-01-12T09:00:00Z"
  }
}
```

The spec called the human-or-agent field `type`. JSON:API forbids an
attribute named `type` or `id`, so it is `kind`, the same word spaces
already use for the same idea. The same rename applies everywhere the
spec puts a `type` attribute on a resource: a post's `author` becomes an
ordinary relationship to a member, whose `kind` says what it is.

A member's `status` is `active` or `deactivated`; an agent can also be
`suspended`. Handles are unique within a workspace, lowercase letters,
digits and hyphens, 2 to 32 characters. `availability` here is the public
summary; RFC 0006 defines availability itself.

### Agents

An agent is a member with `kind: "agent"`, plus attributes that only
agents have:

```json
{
  "type": "members",
  "id": "mem_01JA8T...",
  "attributes": {
    "kind": "agent",
    "display_name": "Triage",
    "handle": "triage",
    "status": "active",
    "description": "Routes new support threads and drafts first replies",
    "scopes": ["threads:read", "posts:write:draft", "requests:write"],
    "requires_approval_for": ["post.publish", "request.assign"],
    "model": { "provider": "anthropic", "name": "claude-sonnet-5-5" },
    "timezone": "Europe/London",
    "created_at": "2026-10-01T09:00:00Z"
  },
  "relationships": {
    "owner": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "spaces": { "data": [{ "type": "spaces", "id": "spc_01JA9S..." }] },
    "acts_on_behalf_of": { "data": null }
  }
}
```

The rules:

- **Every agent has a human owner,** who is accountable for everything it
  does. A member creates agents they own; an admin can create an agent for
  any human member, and transfer an agent between owners.
- **Agents are capped per owner.** The workspace sets how many agents one
  member may own, 5 by default, and an owner or admin can change it.
  A person's MCP assistants have an allowance of their own (RFC 0011).
  Creating or transferring an agent to a member already at the cap is
  `409` `agent-limit-reached`. Lowering the cap leaves existing agents
  where they are and blocks new ones until the member is under it.
- **An agent's scopes cannot exceed its owner's role,** and an agent's
  spaces must be spaces its owner can see. Changing the owner rechecks
  both, and anything the new owner could not grant is removed.
- **Space allow-list.** An agent only sees the spaces in its `spaces`
  relationship, even when its scopes would let it see more. A person's
  MCP assistant can instead follow the spaces the person can see (RFC
  0011).
- **Draft by default.** With `posts:write:draft`, an agent's posts are
  created as drafts that a human with access to the thread publishes or
  discards. Granting `posts:write` lets it publish directly, and the grant
  is audited.
- **Approval rules return a draft.** `requires_approval_for` lists
  *actions* (below), not scopes, which removes the spec's confusion
  between the two. When an agent attempts a listed action, Longhand
  creates the pending object, answers `403` `approval-required`, and adds
  a `draft_awaiting_approval` inbox item for the owner.
- **Decisions stay human.** An agent can draft a decision and never
  publish one, whatever its scopes.
- **Agents cannot manage identity.** No agent can create agents, change
  scopes, approval rules or spaces (its own or another's), invite, or
  change roles, whatever its scopes. Those are human actions on REST or in
  the web app, never on MCP (spec section 13).
- **Owners are accountable.** Deactivating an agent's owner suspends the
  agent, with `agent.suspended`, until an admin transfers it to another
  owner or deactivates it.

An agent is created with `POST /v1/members` and `"kind": "agent"`. Humans
are never created through the API; they join by invitation. Each agent has
OAuth client credentials (below), issued when it is created and shown
once.

### Delegation

`acts_on_behalf_of` names a human the agent is acting for. Only that human
can set it, and only on an agent they own. While it is set:

- every action shows as "Triage for Steve" in clients and in the audit
  log;
- the agent can read that human's inbox and requests, as if it were them,
  within its own scopes and spaces;
- it can never do anything the human could not.

A person's own assistant connecting through MCP is an agent they own with
`acts_on_behalf_of` set to them (RFC 0011).

### Authentication

Each surface authenticates the way it is built for:

| Caller | Authenticates with | Token or session belongs to |
| --- | --- | --- |
| The web app | The starter kit's session, after signing in | The account, with the current workspace's member chosen by the switcher |
| A third-party app acting for a person | OAuth 2.1 authorisation code with PKCE | The person's member in the workspace chosen at consent |
| An agent | OAuth 2.1 client credentials | The agent's member |
| An MCP client | OAuth 2.1 per the MCP authorisation spec (RFC 0011) | An agent's member, or a person's assistant |

Access tokens last one hour. Refresh tokens, for the authorisation code
flow, rotate on every use. A consent screen in the web app shows the
workspace, the scopes requested and what they allow, and an admin can see
and revoke every client and token in the workspace.

### Scopes

A token's scopes are the most it may do. What it actually may do is the
intersection of three things: the token's scopes, the member's role (or,
for an agent, its owner's role), and what the member can see. A web app
session carries every scope the member's role allows.

| Scope | Grants |
| --- | --- |
| `workspace:read` | Read the workspace's name, handle and settings |
| `workspace:write` | Change the workspace's settings. Owners and admins, humans only |
| `members:read` | Read members and their public availability |
| `members:write` | Invite, change roles, deactivate members, and manage agents. Humans only |
| `spaces:read` | List and read visible spaces and their members |
| `spaces:write` | Create, update and archive spaces, and manage their members |
| `threads:read` | Read threads and everything in them: posts, requests and decisions |
| `threads:write` | Create threads, and change their status, owner and deadlines |
| `posts:write:draft` | Create posts as drafts only |
| `posts:write` | Create, edit and publish posts, including publishing drafts |
| `requests:write` | Create requests, and accept, decline, complete, cancel and reopen them |
| `requests:assign` | Assign or reassign a request to someone other than the caller |
| `decisions:write` | Draft decisions, and for humans publish and supersede them |
| `inbox:read` / `inbox:write` | Read and act on the member's own inbox, or their principal's when delegated |
| `briefs:read` | Read briefs the member is the reader of |
| `briefs:write` | Request briefs, and write them as a brief generator |
| `check_ins:read` | Read check-ins and their runs |
| `check_ins:write` | Create check-ins and submit responses |
| `availability:write` | Change the member's own availability |
| `webhooks:write` | Manage webhook subscriptions. Humans only |
| `audit:read` | Read the audit log. Admins and owners only |

The changes from the spec: `workspace:read`, `workspace:write`, `briefs:read`,
`check_ins:read` and `availability:write` are new; `threads:read` is
defined to cover what is inside a thread, which is what the spec's MCP
tools already assumed; `requests:assign` is only needed to assign someone
else, so an assignee reassigning a request back with `requests:write`
alone is allowed; and `members:write`, `workspace:write`, `webhooks:write`
and `audit:read` can never be granted to an agent.

### Actions and how they are checked

ADR 0013 makes state changes `PATCH`es of a resource's state, so the
permission check cannot be on the route. Every write is a named **action**
in the domain, and each action declares the scope it needs, who may take
it, and whether an agent's approval rules apply to it. The same check runs
on every surface, because every surface calls the same Action.

Actions are named `resource.verb`: `thread.resolve`, `request.accept`,
`request.assign`, `post.publish`, `decision.publish`, `member.invite` and
so on. Each RFC lists its resources' actions, with their scope and who may
take them. Those names are what `requires_approval_for` lists, what the
audit log records, and what `insufficient-scope` and `approval-required`
errors name in their `meta.action`.

### Members over their lifetime

| From | Action | To | Who |
| --- | --- | --- | --- |
| (invitation or domain) | `member.join` | `active` | The person joining |
| `active` | `member.deactivate` | `deactivated` | An admin, or an owner for an owner. The member's agents become `suspended` |
| `deactivated` | `member.reactivate` | `active` | An admin |
| `active` | `member.leave` | `deactivated` | The member themselves |
| agent `active` | `agent.suspend` | `suspended` | The owner or an admin |
| agent `suspended` | `agent.resume` | `active` | The owner or an admin, once the owner is active |

Each is a `PATCH /v1/members/{member}` of `status`, as ADR 0013 requires,
apart from joining, which happens in the web app. A deactivated member's
posts, requests and decisions stay where they are and keep their author.
Their open requests and owned threads are flagged to admins, never
reassigned automatically.

### Deleting an account

A person can delete their account from the web app, after entering their
password again. Deletion is refused while they are the last owner of any
workspace: they promote another owner or delete that workspace first, and
the page lists which workspaces are in the way.

Deleting an account:

- deactivates its member in every workspace, emitting
  `member.deactivated` with `reason: "account_deleted"`, which suspends
  any agents the person owns;
- ends every session and revokes every OAuth token issued to the person;
- removes the email address and password immediately, so the address
  can sign up again as a new account with no link to the old one;
- cannot be undone.

What remains of a deleted person in each workspace, their name on what
they wrote, is the retention RFC's to decide. Until then, the deactivated
member keeps its display name.

### Workspace

The workspace is a singleton for the token: `GET /v1/workspace` and
`PATCH /v1/workspace`, of type `workspaces`. It has a `name`, a `handle`,
a default timezone for new spaces and check-ins, the agent cap per
member, the `brief_generator` relationship and `brief_daily_limit`
(RFC 0007), `semantic_search` (RFC 0009), `subscription_approval` (RFC 0010),
`max_assistants_per_member` (RFC 0011), and `created_at`.
Changing it needs `workspace:write`, as action `workspace.update`, by an
owner or admin. The list of email domains is web app only and
owner only.
Deleting a workspace is web app only, owner only, and takes effect after
a 7-day grace period during which it can be restored.

### Audit log

Every action is recorded in the audit log as an `audit_events` resource:

```json
{
  "type": "audit_events",
  "id": "aud_01JAC9...",
  "attributes": {
    "action": "request.assign",
    "surface": "mcp",
    "scope": "requests:assign",
    "occurred_at": "2026-10-08T11:42:00Z",
    "changes": { "assignee": { "from": "mem_01JA7Q...", "to": "mem_01JA7R..." } }
  },
  "relationships": {
    "actor": { "data": { "type": "members", "id": "mem_01JA8T..." } },
    "on_behalf_of": { "data": { "type": "members", "id": "mem_01JA7Q..." } },
    "subject": { "data": { "type": "requests", "id": "req_01JAA4..." } }
  }
}
```

- The log is append-only. Nothing, including an owner, can edit or delete
  an entry.
- `surface` is `rest`, `mcp`, `web` or `system` (scheduled work such as
  staleness and overdue checks).
- `GET /v1/audit-events` lists entries newest first, filterable by
  `filter[actor]`, `filter[kind]` (the actor's), `filter[action]`,
  `filter[surface]`, `filter[subject]` and a date range. It requires
  `audit:read`.
- Failed authorisation attempts are logged too, so an agent repeatedly
  trying an action it may not take is visible.

### Endpoints

| Method | Path | Action |
| --- | --- | --- |
| `GET` | `/v1/me` | The caller's member |
| `GET` / `PATCH` | `/v1/workspace` | Read or update the workspace |
| `GET` | `/v1/members` | List members, `filter[kind]=agent` |
| `POST` | `/v1/members` | Create an agent |
| `GET` | `/v1/members/{member}` | Read a member |
| `PATCH` | `/v1/members/{member}` | Update profile, role, status, or an agent's scopes, spaces, rules, owner or delegation |
| `POST` | `/v1/members/{member}/credentials` | Rotate an agent's client secret, shown once |
| `GET` / `POST` | `/v1/invitations` | List or create invitations |
| `PATCH` / `DELETE` | `/v1/invitations/{invitation}` | Resend or revoke |
| `GET` | `/v1/audit-events` | The audit log |

Signing up, accepting an invitation, joining by domain, creating and
deleting workspaces, managing email domains, deleting an account,
consenting to OAuth clients and managing tokens are web app only.

### Errors

New product-specific errors, added to the index in RFC 0002:

| `code` | Status | When | `meta` |
| --- | --- | --- | --- |
| `last-owner` | 409 | Removing, demoting or deactivating the last owner | |
| `scope-exceeds-owner` | 422 | Giving an agent a scope its owner's role does not allow, or a space its owner cannot see | `scopes`, `spaces` |
| `agent-limit-reached` | 409 | Creating or transferring an agent to a member who already owns the workspace's maximum | `limit`, `owned` |

`insufficient-scope` and `approval-required` errors name the attempted
action in `meta.action`.

### Events

From the spec's catalogue, `member.joined`, `member.updated`,
`member.deactivated`, `agent.scopes_changed` and `agent.suspended`, plus:

| Event | Fires when |
| --- | --- |
| `member.reactivated` | A deactivated member is reactivated |
| `agent.owner_changed` | An agent is transferred to a new owner |
| `agent.resumed` | A suspended agent is resumed |
| `invitation.created` | An invitation is sent |
| `invitation.accepted` | An invitation is accepted, before `member.joined` |
| `invitation.revoked` | An invitation is revoked, or expires |

## Alternatives considered

- **One account per workspace.** Simpler, and wrong for anyone who
  belongs to more than one team, which is everyone who works with
  clients.
- **Agents as API keys rather than members.** Fewer concepts, and a second
  permission model where an agent can end up allowed to do something a
  person in the same role could not.
- **Approval rules keyed by scope.** The spec's starting point. Scopes
  grant families of actions, so approving "`requests:write`" is either too
  coarse to be useful or needs a list of exceptions. Actions are the unit
  people think in.
- **Scopes checked per route.** The usual approach, and impossible once a
  single `PATCH` route performs several actions (ADR 0013).
- **Invitations accepted only by the invited address.** Safer against a
  forwarded link, but it blocks the common case of a work invitation
  accepted from a personal account. The 7-day expiry and revocation limit
  the risk.

## Decisions this records

- **Accounts, workspaces and members are separate,** and the API only
  knows members.
- **Onboarding is web app only:** sign-up, workspace creation, accepting
  invitations, OAuth consent.
- **Agents have a human owner, scopes bounded by the owner's role, and a
  space allow-list,** and can never manage identity.
- **Every write is a named action,** checked for scope, role and
  approval on every surface, and recorded by name in the audit log.
- **Approval rules list actions, not scopes.**
- **A resource's subtype attribute is `kind`, never `type`,** because
  JSON:API reserves `type`.
- **Callers authenticate per surface:** sessions for the web app, OAuth
  2.1 authorisation code with PKCE for third-party apps, client
  credentials for agents.
- **The audit log is append-only and records failed attempts.**
- **v1 signs in with email and password or a passkey,** with optional
  two-factor authentication (amended by RFC 0012).
- **Owners can let a verified email domain join without an invitation,**
  as members.
- **Agents are capped per owning member,** 5 by default.
- **A new workspace starts with a `General` space.**
- **People can delete their account in v1,** except while they are the
  last owner of a workspace.

## Open questions

None. Resolved in review on 2026-10-08:

1. **Sign-in** is email and password only for v1. RFC 0012 later added
   passkeys and two-factor authentication, which the starter kit ships.
2. **Joining by email domain** is in, for addresses the person has
   verified.
3. **Agent creation** stays open to every member, capped per member.
4. **A new workspace** starts with a `General` space.
5. **Account deletion** is in v1.
