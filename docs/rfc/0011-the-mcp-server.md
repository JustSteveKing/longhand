# RFC 0011: The MCP server

- **Status:** Accepted
- **Created:** 2026-10-09
- **Depends on:** RFC 0003, RFC 0004, RFC 0005, RFC 0006, RFC 0007, RFC 0008, RFC 0009, RFC 0010, ADR 0016, ADR 0017, ADR 0020

## Summary

The MCP server gives agents the same domain as the REST API, shaped as
tools, resources and prompts. Agents connecting through MCP are members
with the same scopes, space allow-lists, draft rules, approval rules and
audit trail as anywhere else, because every tool calls the same Action
as the REST endpoint it mirrors. This RFC defines the connection and its
authorisation, who a connection is, the tools, resources and prompts,
how approvals and errors reach a model, and what MCP can never do. It is
section 13 of [the spec](../spec.md), built on `laravel/mcp`.

## Problem

The spec's MCP section predates most of the decisions since. Its tool
list does not know about roll-ups, check-in answers, date proposals,
marking questions answered, or `briefs:read`. Its approvals rely on MCP
elicitation and its resources on resource subscriptions, neither of
which `laravel/mcp` 1.0 supports. It says a person's assistant acts
"with `acts_on_behalf_of` set", without saying where that agent comes
from when someone connects a desktop client. It leaves open how
`If-Match` (ADR 0011) applies to a model that has no habit of sending
versions, and how a refusal reaches a model in a form it can act on.

The spec also has `resolve_thread` "always need human confirmation when
called by an agent", which has no mechanism without elicitation, and
places the MCP server in its own Laravel application.

## Goals

1. Nothing is possible through MCP that the permission model forbids
   through REST, and the reverse.
2. A model only sees tools it can actually use.
3. A refusal tells the model what happened and what to do instead, in
   words and data it can act on.
4. Connecting a person's own assistant is one consent screen, and leaves
   an agent the person owns and can see, limit and revoke.
5. Nothing administrative is reachable from the channel agents work
   through.

## Non-goals

- MCP elicitation and resource subscriptions, until `laravel/mcp`
  supports them. Agents follow threads through the stream or webhooks
  (RFC 0010), or with `since_post`.
- Server-side generation behind prompts. Prompts give the client's model
  context and instructions; the model's work comes back through tools.
- The stdio transport. Longhand is a remote server.
- Tool search (`search_tools` and `execute_tools` in `laravel/mcp`). The
  catalogue is small enough to list.

## Design

### Connection

| Concern | Rule |
| --- | --- |
| Endpoint | `https://mcp.longhand.example/v1`, Streamable HTTP |
| Application | The same Laravel application as the REST API and the web app, on its own route domain, calling the same Actions |
| Authorisation | OAuth 2.1 per the MCP authorisation spec, from the same Laravel Passport authorisation server as REST (ADR 0020) |
| Discovery | Protected resource metadata at `/.well-known/oauth-protected-resource`, listing Longhand's scopes |
| Client registration | Dynamic client registration, as MCP clients expect |
| Audit | Every tool call, allowed or refused, in the audit log with `surface: "mcp"` |
| Rate limits | The REST limits, per token |

`laravel/mcp` registers discovery routes advertising a single `mcp:use`
scope. Longhand defines its own `.well-known` routes instead, which the
package then leaves alone, so the metadata lists the real scopes from
RFC 0003. Passport issues tokens for both surfaces.

The spec ran MCP as a separate application. One application with a
second route domain gives the same separation of surfaces with one
deployment and one copy of the domain; RFC 0013 sets out the layout.

### Who a connection is

Every MCP connection is a member, and there are two kinds:

- **An agent**, connecting with client credentials issued when the agent
  was created (RFC 0003). It is that agent, with its own scopes and
  allow-list.
- **A person's assistant**: a client such as a desktop or IDE assistant,
  connected by a person through the authorisation code flow. The first
  time a person connects a given client to a workspace, the consent
  screen creates an agent for it: owned by the person, with
  `acts_on_behalf_of` set to them, named after the client ("Claude for
  Steve"), marked `assistant: true`, with the scopes the person consents
  to. Reconnecting the same client reuses it. Revoking the client's
  tokens leaves the agent `suspended`; the person can resume or
  deactivate it.

  **Its own allowance.** Assistants do not count against the agent cap
  (RFC 0003). They have their own, the workspace's
  `max_assistants_per_member`, 5 by default, so connecting a laptop and a
  phone does not use up the agents a person builds for their team. Going
  over it is `409` `agent-limit-reached` with `meta.kind`
  `"assistant"`.

  **Its spaces follow the person.** An assistant's allow-list is, by
  default, whatever spaces the person can see, and changes as they join
  and leave spaces, so it never needs maintaining and never outlives the
  person's own access: losing a space revokes the assistant's stream for
  it too (ADR 0054). This is the one exception to RFC 0003's explicit
  allow-lists, and it only ever narrows to what the person has. The
  person can switch it to a fixed list at consent or later, with
  `spaces_follow_principal: false`.

So a person's assistant is never the person. It is an agent under every
agent rule (ADR 0016): drafts by default, no decisions, no identity
management, labelled as "Claude for Steve" everywhere it acts. Delegation
gives it the person's inbox and requests, within its scopes and
allow-list (RFC 0003). A person cannot connect MCP as themselves.

### Tools only appear when they can be used

`tools/list` contains only the tools the connection's scopes allow. Each
tool's `shouldRegister` checks the token, so a model never sees a tool
it would only be refused. A model that sees a tool will try to call it,
and a list trimmed to the real permissions gives fewer refusals and fewer
confused agents.

Role, visibility and approval rules are checked when a tool is called,
not in the list, because they depend on what it is called on.

### Tools

Every tool declares an input and output schema, returns
`structuredContent` holding JSON:API resource objects exactly as the
REST API renders them, and carries annotations (`IsReadOnly`,
`IsIdempotent`, `IsDestructive`) so clients know what they can run
without asking. Each one calls the same Action as its REST endpoint.

**Reading** (all read-only):

| Tool | Does | Scope |
| --- | --- | --- |
| `list_spaces` | Spaces the caller can see | `spaces:read` |
| `list_threads` | Threads, filtered by space, status, purpose, owner | `threads:read` |
| `get_thread` | A thread with its posts, open requests, decisions and summary. `since_post` returns only newer posts | `threads:read` |
| `search` | Hybrid, keyword or semantic search (RFC 0009) | `threads:read` |
| `list_requests` | Requests by assignee or requester and state | `threads:read` |
| `list_decisions` | The decision log, with filters | `threads:read` |
| `get_decision_chain` | A decision's supersession chain | `threads:read` |
| `list_inbox` | The caller's inbox, or their principal's | `inbox:read` |
| `get_availability` | A member's limited availability and `lands_at` | `members:read` |
| `get_brief` | A brief and its items with citations | `briefs:read` |
| `get_check_in_run` | A run, its questions and the caller's response | `check_ins:read` |

**Writing:**

| Tool | Does | Scope | Annotations |
| --- | --- | --- | --- |
| `create_thread` | A thread with its first post, optionally a request | `threads:write` | |
| `post_to_thread` | A post with an intent; a draft unless the agent has `posts:write` | `posts:write:draft` | |
| `edit_post` | Edit a post the caller wrote | `posts:write` | idempotent |
| `create_request` | A request post with its request | `requests:write` | |
| `update_request` | Accept, decline, complete, reassign, cancel, reopen, or propose a due date | `requests:write` | idempotent |
| `mark_question_answered` | Mark the caller's own question answered | `posts:write` | idempotent |
| `draft_decision` | A decision post and its decision, always as a draft | `decisions:write` | |
| `set_thread_status` | Move a thread to `waiting` or back to `open` | `threads:write` | idempotent |
| `propose_resolution` | Propose an outcome for a thread's owner to accept | `threads:write` | idempotent |
| `update_inbox_item` | Mark done, snooze or reopen | `inbox:write` | idempotent |
| `request_brief` | Start a brief for the caller, or their principal | `briefs:write` | |
| `roll_up_thread` | Roll up a thread for everyone (RFC 0007) | `briefs:write` | idempotent |
| `submit_brief` | As the generator, submit a brief, validated whole | `briefs:write` | idempotent |
| `submit_check_in_response` | Answer a check-in run, or say there is nothing to report | `check_ins:write` | idempotent |

Write tools accept an optional `idempotency_key`, which is the REST
idempotency key (RFC 0002), scoped to the member and the tool.

### Agents and resolving threads

The spec's `resolve_thread` "always needs human confirmation when called
by an agent". Without elicitation, that is expressed as a proposal:
`propose_resolution` sets the thread's `proposed_outcome` and
`proposed_by` (added to RFC 0004's thread), and gives the thread's owner
a `draft_awaiting_approval` inbox item. The owner resolves the thread as
usual, starting from the proposal, or clears it. An agent never resolves
a thread itself, on MCP or REST: `thread.resolve` is always human, like
`decision.publish`, and no approval rule or scope changes that.

### Versions

ADR 0011 requires `If-Match` on every change. Models do not keep ETags
well, so MCP splits changes in two:

- **Transitions** (accepting a request, snoozing an item, moving a thread
  to `waiting`) need no version. Each is checked against the resource's
  current state by its lifecycle rules, and one that no longer applies
  is refused as `invalid-transition`, which is the protection a version
  would have given.
- **Content changes** (`edit_post`, a request's `done_when`) require the
  `version` that the reading tool returned for the resource, in its
  `meta.version`. A stale or missing one is refused like a `412` or
  `428`, and the refusal carries the current resource, so the model can
  read it and try again.

### Refusals and errors

A domain refusal is a tool result with `isError: true`, never a protocol
error, so the model reads it. Its text says what happened and what to do
instead, in a sentence, and its `structuredContent` holds the same
JSON:API error object REST would return: `code`, `title`, `detail`,
`meta`. A model that is refused `insufficient-scope` is told which action
it attempted (`meta.action`); one refused `invalid-transition` is told
the current state and what is allowed.

Protocol errors are kept for what is genuinely broken: an unknown tool,
input that fails the schema, an expired token.

### Approvals

When a tool call hits an action in the agent's `requires_approval_for`
(ADR 0018), the tool does not fail. It returns the created draft, with
`approval` in the result's `meta` naming who must approve, and the
approver gets a `draft_awaiting_approval` inbox item, exactly as REST's
`403` `approval-required` does. The model learns that its work is
waiting, not that it was wrong.

The spec's in-session confirmation through elicitation waits for
`laravel/mcp` to support elicitation. When it does, a person's assistant
can ask the person present in the session to approve, as an addition to
the inbox, never instead of it.

### Resources

Read-only context a client can attach without a tool call, with the
`longhand://` scheme (ADR 0002):

| URI | Content |
| --- | --- |
| `longhand://threads/{thread}` | The thread and its published posts as Markdown, with each post's ID inline for citation, and its summary if it has one |
| `longhand://decisions/{decision}` | A decision and its supersession chain |
| `longhand://spaces/{space}/decisions` | The space's active decisions |
| `longhand://me/inbox` | The caller's open inbox, or their principal's |
| `longhand://me/requests` | Open requests to and from the caller |

Each is a URI template, listed only when the token has the scope to read
it, and rendered with the caller's visibility, so a thread resource omits
check-in answers the caller cannot see yet (ADR 0043) just as REST does.
Resource subscriptions are a non-goal for now.

### Prompts

Reusable prompts a client can offer as commands. Each one returns
messages for the client's own model: instructions, and the relevant
resources embedded. Longhand runs no model behind a prompt; whatever the
model produces comes back through tools, as drafts where the rules say
so.

| Prompt | Arguments | Gives the model |
| --- | --- | --- |
| `catch_up` | `since`, optional | The caller's inbox and open requests, with instructions to call `request_brief`, or `get_thread` on what needs them |
| `draft_reply` | `thread` | The thread, with instructions to draft a post with the intent that fits |
| `prepare_handoff` | `member`, `until` | The caller's open requests, waiting threads and recent decisions, with instructions to write a handoff post for the member covering |
| `close_out_thread` | `thread` | The thread, with instructions to call `propose_resolution` and, for a decision thread, `draft_decision` |

### What MCP cannot do

No tool, resource or prompt can:

- manage identity: members, agents, scopes, allow-lists, approval rules,
  invitations, roles, delegation;
- manage webhook subscriptions or the stream's credentials;
- change anyone's availability, including the principal's;
- read the audit log;
- change workspace settings, including the brief generator;
- delete anything, other than discarding the caller's own drafts.

Those stay on REST and in the web app, with a human's token or session.
An agent that could widen its own permissions through the channel it
works through would make the permission model decorative. The scopes
that would allow them cannot be granted to an agent anyway (RFC 0003);
this makes the same rule structural, so no tool for them exists to list.

## Alternatives considered

- **A separate application for MCP.** The spec's layout, and two
  deployments sharing a domain layer that only one of them owns.
- **Connecting MCP as the person.** Simpler for the person, and their
  assistant would publish decisions, resolve threads and manage members
  with their full rights, unlabelled.
- **Assistants under the agent cap.** One cap for two different things: a
  person's own clients, and agents they build for others.
- **Fixed allow-lists for assistants.** Explicit, and out of date the
  first time the person joins a space.
- **Resolving under approval rules.** Consistent with other actions, and
  lets an agent end a conversation for the people in it.
- **Every tool listed, refused at call time.** Models try what they see.
- **Requiring versions for every change.** Correct, and a model would
  fail half its transitions on versions it never kept.
- **Protocol errors for refusals.** The client handles them and the model
  never learns why.
- **Waiting for elicitation before shipping approvals.** Approvals already
  work through the inbox, on every surface.

## Decisions this records

- **MCP runs in the same application as REST and the web app,** on its
  own route domain, calling the same Actions.
- **One Passport authorisation server serves REST and MCP,** with
  Longhand's own discovery metadata listing its real scopes.
- **A person's assistant is an agent they own, created at consent,**
  acting on their behalf; nobody connects MCP as themselves.
- **Assistants have their own allowance,** 5 per member by default, apart
  from the agent cap.
- **An assistant's spaces follow the person** unless they fix them.
- **`tools/list` shows only tools the token's scopes allow.**
- **Transitions need no version on MCP; content changes do.**
- **Refusals are tool results with the JSON:API error object.**
- **Resolving a thread is always human;** agents propose a resolution for
  the owner.
- **No tool exists for administrative actions.**
- **Elicitation and resource subscriptions wait for `laravel/mcp`.**

## Open questions

None. Resolved in review on 2026-10-09:

1. **Assistants** have their own allowance, apart from the agent cap.
2. **Resolving a thread** is always a human action.
3. **An assistant's spaces** follow the person.
