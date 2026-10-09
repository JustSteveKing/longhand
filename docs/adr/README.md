# ADRs

Every ADR, in order, with its status. See [the process](../README.md).

| ADR | Decision | Status |
| --- | --- | --- |
| [0001](0001-specify-in-rfcs-and-record-decisions-in-adrs.md) | Specify in RFCs and record decisions in ADRs, before code | Accepted |
| [0002](0002-the-product-is-longhand-and-events-are-prefixed-longhand.md) | The product is Longhand, and event types are prefixed `longhand.` | Accepted |
| [0003](0003-the-web-app-calls-the-domain-through-inertia.md) | The web app calls the domain through Inertia, not the public API | Accepted |
| [0004](0004-the-rest-api-is-json-api.md) | The REST API is JSON:API 1.1 | Accepted |
| [0005](0005-errors-are-json-api-error-objects-typed-by-apiguide-dev.md) | Errors are JSON:API error objects, typed by the apiguide.dev catalogue | Accepted |
| [0006](0006-the-major-version-is-in-the-url-path.md) | The major version is in the URL path | Accepted |
| [0007](0007-identifiers-are-prefixed-ulids.md) | Identifiers are prefixed ULIDs | Accepted |
| [0008](0008-collections-use-json-api-cursor-pagination.md) | Collections use JSON:API cursor pagination | Accepted |
| [0009](0009-unsupported-query-parameters-are-400.md) | Unsupported query parameters, includes and sorts are 400 | Accepted |
| [0010](0010-every-post-accepts-an-idempotency-key.md) | Every POST accepts an idempotency key | Accepted |
| [0011](0011-if-match-is-required-on-patch-and-delete.md) | If-Match is required on PATCH and DELETE | Accepted |
| [0012](0012-a-resource-the-caller-cannot-see-is-404.md) | A resource the caller cannot see is 404, never 403 | Accepted |
| [0013](0013-state-changes-are-patches.md) | State changes are PATCHes, and actions that produce a resource are creates | Accepted |
| [0014](0014-files-go-directly-to-storage.md) | Files go directly to storage, never through the API | Accepted |
| [0015](0015-accounts-workspaces-and-members-are-separate.md) | Accounts, workspaces and members are separate, and the API knows only members | Accepted |
| [0016](0016-agents-are-members-owned-by-a-human.md) | Agents are members owned by a human, and can never exceed their owner | Accepted |
| [0017](0017-permissions-are-checked-per-named-action.md) | Permissions are checked per named action, not per route | Accepted |
| [0018](0018-approval-rules-list-actions.md) | Agent approval rules list actions, not scopes | Accepted |
| [0019](0019-a-resources-subtype-is-kind.md) | A resource's subtype attribute is `kind`, never `type` | Accepted |
| [0020](0020-callers-authenticate-per-surface.md) | Callers authenticate per surface, with OAuth 2.1 for everything outside the web app | Superseded by ADR 0060 |
| [0021](0021-the-audit-log-is-append-only.md) | The audit log is append-only and records failed attempts | Accepted |
| [0022](0022-multi-resource-creates-use-atomic-operations.md) | Creates that need several resources use JSON:API Atomic Operations, in documented compositions only | Accepted |
| [0023](0023-memberships-and-reactions-are-resources.md) | Space memberships and reactions are resources, not relationships or sub-paths | Accepted |
| [0024](0024-read-position-only-moves-forward.md) | A member's read position in a thread only moves forward, and takes no If-Match | Accepted |
| [0025](0025-admins-see-private-spaces-not-their-contents.md) | Admins see that a private space exists and who is in it, never what is in it | Accepted |
| [0026](0026-mentions-are-read-from-the-body.md) | Mentions are read from the body by the server, and never grant access | Accepted |
| [0027](0027-only-the-asker-marks-a-question-answered.md) | Only the asker marks a question answered, and Longhand reminds them | Accepted |
| [0028](0028-request-history-is-a-transitions-collection.md) | A request's history is a collection of transitions | Accepted |
| [0029](0029-transition-notes-go-in-document-meta.md) | A note about a transition goes in the request document's meta, not in an attribute | Accepted |
| [0030](0030-publishing-a-decision-makes-you-a-decider.md) | Whoever publishes a decision is one of the people who decided it | Accepted |
| [0031](0031-only-an-active-decision-can-be-superseded.md) | Only an active decision can be superseded, so the chain never forks | Accepted |
| [0032](0032-delivery-is-one-pure-function.md) | When something reaches a member is worked out by one pure function | Accepted |
| [0033](0033-recipients-come-from-a-routing-table.md) | Inbox recipients come from a routing table of what happened, never from participation | Accepted |
| [0034](0034-scheduled-items-are-invisible-until-delivered.md) | Scheduled inbox items are invisible to their recipient, and are rescheduled when availability changes | Accepted |
| [0035](0035-atomic-updates-carry-their-etag-in-meta.md) | Updates inside atomic operations carry their ETag in the operation's meta | Accepted |
| [0036](0036-others-see-a-limited-view-of-availability.md) | Others see a limited view of a member's availability, and agents have none of their own | Accepted |
| [0037](0037-generators-read-only-a-source-bundle.md) | A brief generator reads only a source bundle built with the reader's permissions | Accepted |
| [0038](0038-the-built-in-generator-is-an-ordinary-agent.md) | The built-in brief generator is an ordinary agent, with no path of its own | Accepted |
| [0039](0039-briefs-are-snapshots-that-go-stale.md) | A brief is a snapshot that becomes stale, never one that is updated in place | Accepted |
| [0040](0040-anyone-can-roll-up-a-thread.md) | Anyone who can see a thread can roll it up into one summary for everyone | Accepted |
| [0041](0041-check-ins-ask-each-person-in-their-own-hours.md) | Check-ins ask each person at their own local time, and never interrupt | Accepted |
| [0042](0042-a-check-in-response-is-one-update-with-routed-blockers.md) | A check-in response is one update post, with each blocker as a routed question | Accepted |
| [0043](0043-check-in-answers-are-hidden-until-you-answer.md) | In a check-in, a person sees others' answers only once they have answered | Accepted |
| [0044](0044-a-runs-thread-resolves-when-the-next-run-opens.md) | A check-in run's thread resolves when the next run opens | Accepted |
| [0045](0045-search-runs-on-postgresql-alone.md) | Search runs on PostgreSQL alone, with full-text search and pgvector | Accepted |
| [0046](0046-search-applies-visibility-in-the-match.md) | Search applies visibility in the same query as the match, never afterwards | Accepted |
| [0047](0047-embeddings-are-passages-removed-with-their-change.md) | Embeddings are passages, made in queued jobs and removed in the same transaction as the change that makes them wrong | Accepted |
| [0048](0048-search-defaults-to-hybrid.md) | Search defaults to hybrid ranking by reciprocal rank fusion, with semantic search on by default | Accepted |
| [0049](0049-the-test-suite-runs-on-postgresql.md) | The test suite runs on PostgreSQL with pgvector | Accepted |
| [0050](0050-events-are-recorded-in-an-outbox.md) | Events are recorded in an outbox in the same transaction as their change | Accepted |
| [0051](0051-events-are-rendered-for-each-recipient.md) | Every event is rendered for its recipient, with their visibility when it is delivered | Accepted |
| [0052](0052-members-create-subscriptions-with-approval.md) | Webhook subscriptions deliver as a member, and members create them subject to approval | Accepted |
| [0053](0053-the-stream-publishes-cloudevents-to-mercure-topics.md) | The stream publishes CloudEvents to Mercure directly, with permissions encoded in its topics | Accepted |
| [0054](0054-stream-access-is-revoked-by-stream-revoked.md) | Stream access is revoked with a stream.revoked event that every client must obey | Accepted |
| [0055](0055-a-persons-assistant-is-an-agent-they-own.md) | A person's MCP assistant is an agent they own, with its own allowance and spaces that follow them | Accepted |
| [0056](0056-mcp-lists-only-usable-tools-and-has-no-admin-tools.md) | MCP lists only the tools a token can use, and has no administrative tools at all | Accepted |
| [0057](0057-mcp-transitions-need-no-version.md) | On MCP, transitions need no version and content changes do | Accepted |
| [0058](0058-resolving-a-thread-is-always-human.md) | Resolving a thread is always a human action, and agents propose resolutions | Accepted |
| [0059](0059-mcp-runs-in-the-same-application.md) | MCP runs in the same application as REST and the web app, with one Passport authorisation server | Accepted |
| [0060](0060-callers-authenticate-per-surface-and-people-may-use-passkeys.md) | Callers authenticate per surface, and people sign in with a password or a passkey | Accepted |
| [0061](0061-the-web-app-keeps-rests-guarantees-itself.md) | The web app keeps REST's guarantees itself | Accepted |
| [0062](0062-the-web-app-stays-live-from-the-public-stream.md) | The web app stays live from the public CloudEvents stream, with Inertia partial reloads | Accepted |
| [0063](0063-the-inbox-is-home-and-the-palette-runs-commands.md) | The inbox is the web app's home, and the command palette runs commands as well as searching | Accepted |
| [0064](0064-domain-code-lives-in-src-in-eight-contexts.md) | Domain code lives in `src/` under `Longhand\`, in eight contexts that meet only through Actions, queries and events | Accepted |
| [0065](0065-every-action-runs-through-one-runner.md) | Every Action runs through one runner that checks, audits and records events in one transaction | Accepted |
| [0066](0066-visibility-is-one-query-with-embargoes.md) | Visibility is one query owned by Conversations, and other contexts' rules reach it as embargoes | Accepted |
| [0067](0067-search-owns-its-projections.md) | Search owns its projections, kept in step in the same transaction as each change | Accepted |
| [0068](0068-prefixed-ulids-are-the-primary-keys.md) | Prefixed ULIDs are the primary keys | Accepted |
| [0069](0069-development-runs-in-sail.md) | Development runs in Sail, on the same PostgreSQL and a Mercure hub, with database queues in v1 | Accepted |
| [0070](0070-models-go-through-the-laravel-ai-sdk.md) | Briefs and embeddings go through the Laravel AI SDK, with Anthropic for briefs and Voyage AI for embeddings | Accepted |
