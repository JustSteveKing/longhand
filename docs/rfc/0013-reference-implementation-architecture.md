# RFC 0013: Reference implementation architecture

- **Status:** Accepted
- **Created:** 2026-10-09
- **Depends on:** RFC 0001 to RFC 0012

## Summary

How the Laravel reference implementation is put together so that every
rule in RFCs 0002 to 0012 has exactly one home. Domain code lives in
`src/` under `Longhand\`, split into bounded contexts that talk only
through events and each other's public Actions and queries; `app/` holds
the four surfaces (web, REST, MCP, console) and nothing else. Every use
case is one Action, run through one runner that checks permissions,
records the audit entry and writes the outbox in a single transaction.
This RFC sets out the layout, the contexts, the Action runner, the
surfaces' layers, identifiers, the runtime (PostgreSQL with pgvector,
FrankenPHP with Mercure, queues), how it is tested and checked, and the
dependencies it adds. It is Appendices A and B of [the spec](../spec.md),
brought up to date.

## Problem

The RFCs make promises that only hold if the code is shaped to keep
them. "Every surface calls the same Action" fails the first time a
controller does its own check. "Visibility in the same query as the
match" fails when one query forgets a rule. "An event if and only if the
change committed" fails when an event is published from the wrong place.
The spec's appendices describe a structure for this, written before
JSON:API, the outbox's role in rendering, search, the action names of ADR
0017, and the decision to run MCP in the same application.

Some things also have no owner yet. Search reads threads, posts and
decisions, which belong to two contexts, while contexts may not read each
other's models. Check-ins hide posts from some members (ADR 0043), while
posts belong to Conversations. And the repository is still the starter
kit: in-memory SQLite, no `src/`, PHPStan over `app/` only, and none of
Passport, the AI SDK or the Mercure client installed.

## Goals

1. Each rule has one place in the code, and a test that fails if it is
   bypassed.
2. A new use case is one Action, reachable from any surface with a thin
   adapter.
3. Contexts can be read, tested and changed one at a time.
4. What runs in development, in tests and in production is the same
   database and the same hub.
5. The structure is checked by tests, not by memory.

## Non-goals

- Splitting contexts into separate services or databases.
- A deployment platform. This RFC says what runs, not where.
- Event sourcing. State is stored as state; events are an outbox.
- Multi-region or sharding.

## Design

### Layout

```text
src/                                  # Longhand\, the domain and its Actions
  Identity/
  Conversations/
  Commitments/
  Attention/
  Briefs/
  CheckIns/
  Search/
  Integration/
  Shared/                             # IDs, Actor, Urgency, durations, the Action runner
app/                                  # App\, the surfaces
  Http/
    Web/                              # Inertia controllers, form requests, page queries
    Api/V1/                           # JSON:API controllers, requests, resources
    Middleware/                       # negotiation, idempotency, versions, request IDs
  Mcp/                                # servers, tools, resources, prompts
  Console/
  Exceptions/                         # ErrorCode, error rendering per surface
  Providers/                          # one per context
routes/
  web.php
  api.php                             # /v1, JSON:API
  ai.php                              # MCP, on its own route domain
tests/
  Arch/
  Unit/                               # src, no HTTP
  Feature/                            # each surface, through its own transport
  Browser/
```

`composer.json` autoloads `Longhand\\` from `src/` (ADR 0002). `src` never
depends on `App`, on HTTP, on Inertia or on `laravel/mcp`.

### Bounded contexts

| Context | Owns |
| --- | --- |
| `Identity` | Accounts, workspaces and settings, invitations, email domains, members, agents and assistants, scopes, actions, delegation, the audit log |
| `Conversations` | Spaces, memberships, threads, posts, drafts, mentions, reactions, uploads, read positions, visibility |
| `Commitments` | Requests, transitions, date proposals, decisions and supersession |
| `Attention` | Availability, away periods, the delivery policy, inbox items, digests, email |
| `Briefs` | Briefs, source bundles, citations, roll-ups, feedback, the built-in generator |
| `CheckIns` | Check-ins, runs, responses, reminders |
| `Search` | The search projections, keyword and semantic search, embeddings |
| `Integration` | The outbox, CloudEvents mapping and rendering, webhooks, the stream |

`Search` is new: the spec had no owner for it. RFC 0001's seven contexts
become eight.

Contexts talk through **domain events** and each other's **public
Actions and queries**, never through each other's models or tables. Each
context's public surface is the classes in its `Features/` and `Queries/`
directories and its `Events/`; everything else is internal.

### Visibility has one owner

Who can see a space, thread or post is Conversations' to answer, through
one public query, `Visibility`, which every read path calls: the web
app's page queries, the JSON:API controllers, MCP tools, briefs' source
bundles, the search projections and the event renderer. It is built as
query constraints, not as a check on loaded models, so it applies inside
the same SQL as the read (ADR 0046).

Rules from other contexts reach it as data, not code. Check-ins' answer
first (ADR 0043) is an **embargo** on a post: CheckIns asks
Conversations, through a public Action, to hold a post from every member
of the run who has not answered yet, and to lift the hold for a member
when they answer or their window ends. Conversations knows nothing about
check-ins; it knows posts can be embargoed.

### Actions and the runner

Every use case is one Action class in its context's `Features/`, with a
payload DTO, and is named with its ADR 0017 action name:

```php
#[Action('thread.resolve', scope: 'threads:write', approvable: false)]
final readonly class ResolveThread
{
    public function handle(AuthorisedActor $actor, ResolveThreadPayload $payload): Thread { /* ... */ }
}
```

`AuthorisedActor` carries the member, whom they act for, the surface, and the
scopes of the token or session. No Action reads the request or the
authenticated user itself.

Actions are only ever invoked through the **Action runner**. An Action's
`handle` receives an `AuthorisedActor` that only the runner can construct, after it
has checked permissions, so calling an Action directly from a controller
does not type-check; the rule is enforced by the type system rather than
by review. The runner, for every call, on every surface:

1. opens a database transaction;
2. checks the action against the actor: scopes, role, the agent rules of
   ADR 0016, visibility, and the action's own policy;
3. if the actor is an agent and the action is in its
   `requires_approval_for`, runs the Action's draft path instead and
   returns an approval result;
4. runs the Action, whose model methods enforce lifecycles
   (`$thread->resolve($outcome)`; nothing sets `status` directly);
5. writes the audit entry and the outbox rows for the domain events the
   Action recorded;
6. commits.

A refused call is audited after the rollback, on its own, so refusals
are recorded without anything they attempted. The idempotency layer
wraps the runner for creates, keyed by member and route or tool.

### Events inside and out

- **Domain events** are PHP classes recorded by Actions. They are
  internal and never serialised for anyone outside.
- **In-transaction listeners** keep invariants that must hold at commit:
  scheduling inbox items, updating search projections, removing
  passages (ADR 0047), lifting embargoes.
- **After-commit listeners** are queued and do slow or external work:
  embeddings, webhook delivery, publishing to the hub, email, brief
  generation by the built-in generator.
- **Integration** maps domain events to CloudEvents and renders them per
  recipient (ADR 0051). It is the one context that subscribes to every
  other context's events, and it never reads their models; it renders
  resources through the same public queries the API uses.

### Search owns projections

Search keeps its own tables rather than reading Conversations' and
Commitments': `search_documents`, one row per searchable thread, post and
decision with its text, a generated `tsvector`, and the workspace, space,
status and embargo data visibility needs; and `search_passages`, the
embeddings (RFC 0009). In-transaction listeners update documents and
remove passages as the source changes, so keyword search still changes
in the same transaction as the edit, as RFC 0009 requires. This amends
RFC 0009, which put the `tsvector` columns on each source table; the
behaviour is the same, and the boundary between contexts holds.

### The surfaces

**Web** (`app/Http/Web`): Inertia controllers, final and invokable, each
building a payload from its form request and calling the runner; page
queries that shape props (RFC 0012).

**REST** (`app/Http/Api/V1`): controllers built the same way, plus:

- JSON:API resources on Laravel 13's `JsonApiResource`, which handles
  resource objects, relationships and `include`; related resources are
  always loaded through `Visibility`, so an invisible one is never
  included (ADR 0012);
- middleware for media type negotiation and `ext`/`profile` parameters,
  unknown query parameters (ADR 0009), `Idempotency-Key`, ETags and
  `If-Match`, request IDs and rate-limit headers;
- the cursor pagination profile, filters and sorts, from one declaration
  per collection;
- the atomic operations endpoint, which accepts only the compositions
  registered with it (ADR 0022) and runs their Actions in one runner
  transaction.

**MCP** (`app/Mcp`): tools, resources and prompts on `laravel/mcp`, each
tool building a payload and calling the same runner, with
`shouldRegister` reading the token's scopes (ADR 0056).

**Console**: scheduled work (staleness, overdue requests, check-in runs,
digests, reminders, retention) runs Actions with a `system` actor.

**Errors**: `App\Exceptions\ErrorCode` is one enum holding every error
code, its status, title and `links.type` URL, apiguide.dev ones and
Longhand's own, so no URL is written twice. Domain exceptions map to a
code; each surface renders it its way: JSON:API error objects for REST,
`isError` results for MCP, Inertia errors for the web app.

### Identifiers

Primary keys are the prefixed ULIDs themselves (`thr_01JA9X...`), stored
as text, generated by a model trait from each model's prefix. They are
what the API shows, what the outbox stores and what logs contain, so an
ID means the same thing everywhere and needs no translation at the edge.

### Runtime

| Piece | Development | Tests and CI | Production |
| --- | --- | --- | --- |
| Database | PostgreSQL 18 with pgvector, in Sail | The same image: Sail's `testing` database locally, a service container in CI | PostgreSQL with pgvector 0.8 or later |
| Hub | A Mercure hub container, in Sail | A fake hub, recording publishes | FrankenPHP's built-in Mercure hub |
| Application | Sail's application container, running `composer dev` (server, queue, scheduler, Vite) | Pest | FrankenPHP |
| Queue | The database queue | Run inline | The database queue in v1, a worker per queue: `default`, `outbox`, `embeddings`, `email` |
| Mail | Mailpit, in Sail | Faked | A mail provider |
| Models | The configured providers, or none | Faked | Anthropic for briefs, Voyage AI for embeddings |

**Development runs in containers, with Sail.** Sail is already installed.
Its `compose.yaml` runs the application, PostgreSQL, a Mercure hub and
Mailpit, so nothing but Docker is needed on the machine. Sail's
PostgreSQL service uses the `pgvector/pgvector` image in place of plain
`postgres`, at the same major version, and keeps Sail's script that
creates the `testing` database. Sail has no Mercure service, so the hub
is added to `compose.yaml` by hand, with the `dunglas/mercure` image,
configured with the same JWT keys the application signs with.

**Queues use the database in v1,** everywhere, so the whole runtime is
PostgreSQL and one hub. The outbox and embedding queues are the ones that
would feel it first at scale; moving them to Redis is a configuration
change when that day comes, not a design change.

**Models go through the Laravel AI SDK,** for both jobs. The SDK picks
the provider per call from configuration, and Anthropic offers no
embeddings model, so the defaults are two providers behind one SDK:
Anthropic (Claude) for the built-in brief generator, and Voyage AI for
embeddings, which the SDK supports. Either can be swapped in
`config/ai.php`. Tests use the SDK's own fakes, `Embeddings::fake()`
among them, so no test calls a model.

### Testing and checks

- **PostgreSQL in tests** (ADR 0049): `phpunit.xml` points at the
  `testing` database Sail creates; parallel tests get one database per
  process.
- **Fakes**: embeddings return deterministic vectors, the hub records
  what was published, webhooks go to faked HTTP, and time is travelled,
  so no test calls a model, a hub or the network.
- **Unit tests** cover `src` with no HTTP: the delivery policy with Pest
  datasets across timezones, away periods, focus blocks and daylight
  saving changes; lifecycles; citation validation; fusion scoring.
- **Feature tests** go through each surface's own transport: Inertia
  responses, JSON:API documents, MCP tool calls. The permission matrix
  for each action is one dataset run against all three surfaces, so the
  surfaces cannot drift.
- **Browser tests** cover the flows that only make sense in a browser,
  with Pest's browser plugin.
- **Architecture tests** keep the structure:

```php
arch('the domain does not depend on the application')
    ->expect('Longhand')->not->toUse('App');

arch('the domain knows nothing of transports')
    ->expect('Longhand')->not->toUse(['Illuminate\Http', 'Illuminate\Routing', 'Inertia', 'Laravel\Mcp']);

arch('contexts use each other only through features, queries and events')
    ->expect('Longhand\Attention')->not->toUse(['Longhand\Conversations\Models', 'Longhand\Commitments\Models']);
    // repeated for every pair of contexts

arch('controllers are final, readonly and invokable')
    ->expect('App\Http')->classes()->toBeFinal()->toBeReadonly()->toBeInvokable();
```

- **Static analysis**: PHPStan covers `src/` as well as `app/`, at level
  7, as the starter kit set it.
- **CI**: the existing workflow gains a PostgreSQL service container and
  the test database settings; `composer ci:check` stays the one command.

### Dependencies to add

| Package | For | RFC |
| --- | --- | --- |
| `laravel/passport` | The OAuth 2.1 server for REST and MCP | 0003, 0011 |
| `laravel/ai` | Embeddings, and the built-in brief generator | 0007, 0009 |
| `symfony/mercure` | Publishing CloudEvents to the hub | 0010 |
| `pestphp/pest-plugin-browser` (dev) | Browser tests | 0012 |

`laravel/mcp` is already installed. The `src/` directory and these
packages need the approval Boost's `AGENTS.md` asks for, which accepting
this RFC gives.

## Alternatives considered

- **Everything in `app/`, Laravel's default.** Fine for a small app, and
  nothing stops a controller reaching into a model from another context.
- **Search reading source tables directly.** Fewer tables, and a context
  that depends on two others' schemas.
- **Check-in rules inside Conversations' visibility.** Direct, and
  Conversations would depend on CheckIns.
- **Authorisation in policies called from controllers.** Laravel's
  default, and one forgotten call away from a surface without checks.
- **Integer keys with prefixed IDs at the edge.** Smaller indexes, and a
  translation at every boundary, logs that do not match the API, and
  enumerable keys.
- **Docker Compose for services, the application on the host.** Lighter,
  and every machine needs the right PHP, extensions and Node.
- **Redis from the start.** Faster queues, and a second datastore before
  anything needs one.
- **One model provider for both jobs.** Simpler configuration, and either
  briefs or embeddings from a provider that is not the best at them;
  Anthropic has no embeddings model at all.
- **Laravel Octane.** Worth adding when profiling says so; FrankenPHP
  serves the application without it.

## Decisions this records

- **Domain code lives in `src/` under `Longhand\`, split into eight
  contexts,** with `Search` added; `app/` holds the surfaces.
- **Contexts use each other only through public Actions, queries and
  events.**
- **Visibility is one public query owned by Conversations,** applied as
  query constraints on every read path; check-ins use embargoes.
- **Every Action runs through one runner** that checks, audits and writes
  the outbox in one transaction.
- **Search owns its projections,** updated in the same transaction
  (amends RFC 0009).
- **Prefixed ULIDs are the primary keys.**
- **One `ErrorCode` enum,** rendered per surface.
- **Development runs entirely in Sail containers,** with PostgreSQL on
  the pgvector image and a Mercure hub added; production runs FrankenPHP
  with its built-in hub.
- **The same PostgreSQL with pgvector in development, tests, CI and
  production.**
- **Queues use the database in v1,** with Redis a later configuration
  change.
- **Both model jobs go through the Laravel AI SDK:** Anthropic for
  briefs, Voyage AI for embeddings, by default.
- **The permission matrix is tested on all three surfaces from one
  dataset.**

## Open questions

None. Resolved in review on 2026-10-09:

1. **Queues** use the database in v1, and can move to Redis later.
2. **Models** go through the Laravel AI SDK, which handles embeddings as
   well as text; since Anthropic has no embeddings model, briefs default
   to Anthropic and embeddings to Voyage AI.
3. **Development** runs in Sail, with everything in containers.
