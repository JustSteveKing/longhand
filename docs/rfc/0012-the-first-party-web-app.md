# RFC 0012: The first-party web app

- **Status:** Accepted
- **Created:** 2026-10-09
- **Depends on:** RFC 0003, RFC 0004, RFC 0005, RFC 0006, RFC 0007, RFC 0008, RFC 0009, RFC 0010, ADR 0003, ADR 0011, ADR 0017

## Summary

The web app is where people use Longhand: the React client from the
Laravel starter kit, served through Inertia. Its controllers call the
same Actions as REST and MCP, so it can do nothing the domain forbids,
and it is the only place for onboarding, consent and identity
management. This RFC defines how the web app reaches the domain, how it
keeps REST's guarantees without going through REST, its screens, how it
stays live, and how it presents the rules the other RFCs set. RFC 0001
listed it with no spec section of its own; this is that section.

## Problem

ADR 0003 decided the web app calls the domain through Inertia rather
than the public API. That leaves several things to settle. REST gets
idempotency keys, `If-Match`, JSON:API errors and scope checks from its
middleware; the web app has none of that unless it is designed in. The
product's ideas (intents, urgency and when something lands, drafts
waiting for a person, answer-first check-ins, roll-ups) only work if the
screens make them obvious. Live updates could come from Laravel's
`mercure` broadcast driver and Echo, or from the CloudEvents stream RFC
0010 defined. And the starter kit arrives with things the RFCs did not
plan for, among them two-factor authentication and passkeys, where RFC
0003 said v1 signs in with email and password only.

## Goals

1. The web app can do exactly what the domain allows a person, and no
   more, through the same Actions as every other surface.
2. A double-click never posts twice, and nobody overwrites a change they
   have not seen.
3. Opening Longhand shows what needs you, not a stream of what happened.
4. Every product rule is visible where it applies: when a message will
   land, that a post is an agent's draft, why something is in your inbox.
5. Pages stay current without polling, and nothing notifies a person
   except an inbox delivery.

## Non-goals

- Native mobile and desktop apps. The web app is responsive down to phone
  width.
- Offline use.
- A design system beyond the starter kit's components and Tailwind.
- Theming per workspace.

## Design

### Controllers call Actions

Every web route is an Inertia controller that validates input with a
form request, calls an Identity, Conversations, Commitments, Attention,
Briefs or CheckIns Action, and either renders a page or redirects. A
controller contains no domain rule. The Action does the permission check
(ADR 0017), records the audit entry with `surface: "web"`, and writes the
outbox event, exactly as it does for REST and MCP.

A web session carries every scope the member's role allows (RFC 0003),
for the workspace chosen in the switcher. Routes are typed on the client
with Wayfinder, which the starter kit already uses, so a React component
calls a controller by name and never builds a URL.

### Keeping REST's guarantees

The web app does not go through the API's middleware, so it keeps the
same guarantees its own way:

- **Idempotency.** Every form that creates something sends an
  `Idempotency-Key` header, generated when the form opens. The web
  routes that create run through the same idempotency layer as REST
  (RFC 0002), keyed to the member, so a double submit or a retried
  request creates one thing.
- **Versions.** Every page that edits a resource receives its `version`,
  the same value as its ETag, and sends it back. A stale version is
  refused, as ADR 0011 requires, and the page reloads the resource and
  shows what changed instead of overwriting it.
- **Errors.** Validation failures come back as Inertia's form errors,
  keyed by field. Domain refusals (`invalid-transition`,
  `thread-not-open`, `approval-required` and the rest) come back with
  their `code`, and the client shows a message from one catalogue keyed
  by those codes, so the same refusal reads the same everywhere.
- **Visibility.** Page props are built by query classes that apply the
  same visibility rules as the API's, not by filtering in the
  controller. Something the person cannot see is a `404` page, never a
  `403` (ADR 0012).

Page props are shaped for their screens, not JSON:API documents: the web
app is a client of the domain, not of the API (ADR 0003).

### Onboarding

The flows RFC 0003 put in the web app only:

1. **Sign up and verify** with the starter kit's registration and email
   verification.
2. **Create a workspace:** a name and a handle. The workspace starts with
   `General` and the built-in `Longhand` brief generator.
3. **Set your hours:** timezone from the browser, working hours (Monday
   to Friday, 09:00 to 17:00 by default), and the incidents setting shown
   already on (RFC 0006), so every default is one the person has seen.
4. **Invite teammates,** or skip. Owners can also add email domains here.
5. **Join** by invitation link or, for a verified address at a listed
   domain, from a list of workspaces they may join.

A person in several workspaces switches between them from the sidebar.

### Screens

**Inbox is home.** Signing in lands on the inbox, never on a space.

| Screen | What it does |
| --- | --- |
| Inbox | Delivered items grouped by tier, each with its reason in words ("Priya asked you", "Overdue since Tuesday"). Done, snooze and reopen, one at a time or selected together. Today's digest, and past ones |
| My work | Requests you owe and requests you are waiting on, by due date, with overdue and proposed dates marked |
| Space | Its threads by last activity, with status, purpose, stale and waiting badges, and tabs for decisions and check-ins. Muted spaces sit collapsed at the bottom of the sidebar |
| Thread | Its summary first, when it has been rolled up, with the full thread a click away. Posts in order, each with its intent and, for agents, an agent label ("Triage for Steve"). Agents' drafts inline, with Publish and Discard. Banners for waiting, a proposed resolution, and closed threads, with Reopen |
| Composer | Intent as a small choice, urgency within the space's limits, and, before sending, when it will land for each recipient ("lands at 09:30 their time"; "away until the 27th, suggest Priya instead?") from the delivery summary (RFC 0006). A request form with assignee, due date and done-when; a decision form with summary, rationale and who decided |
| Resolve | Outcome, and for a decision thread the decision, in one step; open requests listed if they block it |
| Decisions | The decision log with its filters, and each decision with its chain |
| Command palette | Cmd+K, from anywhere: search, and commands (below) |
| Search | The full results page, hybrid by default, with highlights and filters |
| Catch up | A brief for you, with every item linked to what it cites, and Flag on each item |
| Check-ins | The standup template in one step; the answer form; each run's thread, hiding others' answers until you have answered (ADR 0043) |
| People | Members with their limited availability ("working now", "back Monday"). Agents, with their owner, scopes, spaces and approval rules |
| Settings | Profile, availability, away periods, email, security, connected assistants, account deletion |
| Admin | Members and invitations, roles, email domains, workspace settings, agents, webhooks with their approvals and deliveries, OAuth clients and tokens, the audit log |
| Consent | The OAuth screen for third-party apps and MCP clients (RFC 0011): the workspace, the scopes in words, and for an assistant its spaces |

**The command palette** is search and commands in one box. Typing
searches (RFC 0009); typing a verb, or `>` first, narrows to commands:

- **Go to:** the inbox, my work, decisions, any space, thread or person.
- **Create:** a new thread in the current space, a request, a check-in.
- **Here:** catch me up, roll up this thread, resolve, wait, reopen, mute
  this space, mark this question answered.
- **Inbox:** done, snooze until a chosen time.
- **Me:** set an away period, change working hours.

Each command calls the same controller as the button it stands for. Like
MCP's tool list (ADR 0056), the palette only offers commands the person
can perform where they are: no Resolve for someone who is not the
thread's owner, no admin commands for a member. Commands have keyboard
shortcuts, shown beside them.

Every time is shown in the viewer's timezone, and where it matters, also
in the other person's ("09:30 for Priya, 14:00 for you").

### Staying live

The web app reads the same CloudEvents stream as everyone else (RFC
0010), with the hub cookie RFC 0010 gives first-party clients, rather
than Laravel's `mercure` broadcast driver and Echo:

- One `EventSource` per tab, opened after sign-in. The web app sets and
  renews the hub cookie, which lives for at most 10 minutes.
- Each event type maps to the page props it affects, and the client asks
  Inertia for a partial reload of those props only. The server stays the
  source of truth, and the rendering path is the one a normal visit uses.
- `inbox.item_delivered` is the only event that notifies: a toast, and a
  browser notification if the person has allowed them, which the web app
  asks for once, from the inbox, never on first load.
- `stream.revoked` renews the cookie and reconnects, which is all a
  removed member's tab needs (ADR 0054).
- A dropped connection resumes from the last event, and a gap is filled
  from `/v1/events` (RFC 0010).

This gives one real-time path to build, test and reason about, with the
permissions from ADR 0053 applying to the web app exactly as to any
client. Echo would add a second set of channels and authorisation rules
for the same events.

### What the starter kit brings

The starter kit is kept as it is: registration, email verification,
password reset, profile, appearance, and the security page with
Fortify's two-factor authentication and passkeys, both enabled in
`config/fortify.php`. The kit's `dashboard` page becomes the inbox, and
`welcome` the signed-out landing page.

**This changes RFC 0003's sign-in decision.** RFC 0003 said v1 signs in
with email and password only. With this RFC, a person signs in with email
and password or with a passkey, and can add two-factor authentication
(an authenticator app, with recovery codes) to their account. These are
account settings, so they cover every workspace the person belongs to.
Requiring two-factor authentication for a whole workspace is not in v1.
ADR 0060 supersedes ADR 0020 to record it.

### Testing

Controllers are covered by feature tests through Inertia's testing
helpers: the page rendered, the props it received, the redirect and the
errors. The flows that only make sense in a browser (onboarding, posting
and seeing it land, publishing an agent's draft, answering a check-in)
are browser tests. RFC 0013 sets out the tooling.

## Alternatives considered

- **The web app as a client of the public API.** Rejected in ADR 0003:
  OAuth tokens in the browser and a second network hop for every screen.
- **Echo and Laravel's `mercure` driver for live updates.** Less to write,
  and a second set of channels, payloads and authorisation for events the
  stream already carries, with the stream's permissions.
- **Applying events to client state directly.** Faster to render, and
  every screen re-implements what the server already renders.
- **Removing the kit's passkeys and two-factor authentication** to match
  RFC 0003. Less to support, and a weaker account for no gain; neither
  adds a way in that is less safe than a password.
- **A search-only palette.** Simpler, and the keyboard path through the
  product stops at finding things.
- **A space as the home screen.** What chat tools do, and the stream
  Longhand exists to replace.
- **JSON:API documents as page props.** One shape everywhere, and screens
  written against a format built for API clients.

## Decisions this records

- **Web controllers validate, call an Action, and render or redirect;**
  they hold no domain rules.
- **The web app keeps REST's guarantees itself:** idempotency keys on
  creates, versions on edits, one error catalogue keyed by error code,
  and visibility in the query layer.
- **The inbox is home.**
- **The web app stays live from the public CloudEvents stream,** with
  Inertia partial reloads, not Echo.
- **Only `inbox.item_delivered` notifies,** and browser notifications are
  asked for from the inbox, once.
- **Times are shown in the viewer's timezone, and the other person's
  where it matters.**
- **The command palette searches and runs commands,** offering only
  commands the person can perform.
- **People sign in with email and password or a passkey,** with optional
  two-factor authentication, as the starter kit ships.

## Open questions

None. Resolved in review on 2026-10-09:

1. **Two-factor authentication and passkeys** are both kept.
2. **The command palette** runs commands as well as searching.
