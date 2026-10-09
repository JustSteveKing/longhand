# RFC 0009: Search

- **Status:** Accepted
- **Created:** 2026-10-09
- **Depends on:** RFC 0002, RFC 0004, RFC 0005, RFC 0008, ADR 0012, ADR 0025, ADR 0043
- **Amended by:** RFC 0013, API contract review, 2026-10-09

## Summary

Search finds threads, posts and decisions the caller can see, by the
words in them or by what they mean. Keyword search uses PostgreSQL's
full-text search; semantic search uses embeddings stored in pgvector,
generated with the Laravel AI SDK and queried with Laravel 13's vector
support. Both apply the same visibility rules before anything is ranked,
so neither can surface something the caller could not open. This RFC is
section 11 of [the spec](../spec.md).

## Problem

Decisions and answers are only worth recording if people can find them
again, and people rarely remember the words that were used. Keyword
search finds "SQS" when you search for "SQS"; it does not find the
thread about "the billing queue" when you ask "what did we pick for
queueing?". Semantic search does, but it needs embeddings, a vector
index, a model provider, and care, because a nearest-neighbour index
has no idea who is allowed to see what.

The spec says the right thing ("permission filtering ... applied before
ranking") and leaves the how open. Its query parameters (`q`, `types`,
`mode`, `after`) are not JSON:API's, and RFC 0002 allows only those. And
RFC 0001 chose PostgreSQL while the test suite runs on in-memory SQLite,
which has neither full-text search of this kind nor vectors.

## Goals

1. Search never returns, ranks by, highlights or counts anything the
   caller cannot open.
2. Keyword search handles exact phrases, exclusions and "or", the way
   people type them into any search box.
3. Semantic search finds things by meaning, in the same request shape,
   with the same filters.
4. Edited and deleted content stops matching its old text immediately.
5. The whole design runs on PostgreSQL alone: no search service, no
   vector database.

## Non-goals

- Searching members, spaces, requests or briefs. Requests are found
  through their posts, and members and spaces through their own lists.
- Search across workspaces.
- Saved searches and alerts.
- Languages other than English for stemming. Semantic search works
  across languages; keyword search stems English only.
- Searching old revisions of edited posts.

## Design

### The request

```http
GET /v1/search?filter[q]="billing queue" -redis&filter[type]=posts,decisions&filter[space]=spc_01JA9S...
```

| Parameter | Effect |
| --- | --- |
| `filter[q]` | The query, 1 to 500 characters. Required |
| `filter[mode]` | `hybrid` (the default), `keyword` or `semantic` |
| `filter[type]` | Any of `threads`, `posts`, `decisions`. Defaults to all three |
| `filter[space]`, `filter[thread]` | Limit to spaces or threads |
| `filter[author]` | Posts by these members; decisions they decided |
| `filter[intent]` | Posts with these intents |
| `filter[thread_status]` | Threads with these statuses |
| `filter[decision_status]` | Decisions with these statuses |
| `filter[after]`, `filter[before]` | Created in this range |

The spec's `q`, `types`, `mode`, `after` and `before` become filters,
since RFC 0002 allows only JSON:API's own parameters. `mode` is a filter
on how `q` matches, which is close enough to sit in the family. Results
are ordered by relevance; `sort=-created_at` or `sort=created_at` orders
the matches by date instead. Pagination is the cursor profile, as
everywhere, with at most 200 matches in all for semantic and hybrid
search.

A `filter` that does not apply to any requested type (`filter[intent]`
with only `threads`) is `400` `invalid-query-parameter`.

### The response

A heterogeneous collection of full resources, each with how it matched
in its resource `meta`:

```json
{
  "data": [
    {
      "type": "decisions",
      "id": "dec_01JAB0...",
      "attributes": { "summary": "Use SQS for the billing queue", "...": "..." },
      "relationships": { "thread": { "data": { "type": "threads", "id": "thr_01JA9X..." } } },
      "meta": {
        "score": 0.82,
        "highlight": {
          "field": "summary",
          "text": "Use SQS for the billing queue",
          "ranges": [[20, 33]]
        }
      }
    }
  ],
  "included": [{ "type": "threads", "id": "thr_01JA9X...", "...": "..." }],
  "links": { "next": "/v1/search?filter[q]=...&page[after]=..." }
}
```

- **`score`** is relative to this query only; it means nothing across
  queries or modes.
- **`highlight`** is plain text with the matched character ranges, never
  HTML, so a client marks it up itself and nothing in a post can inject
  markup through it. For a keyword match it is the best fragment with the
  matching terms; for a semantic match it is the passage that matched
  best, with no ranges. In hybrid search, a result found both ways gets
  the keyword highlight, and `meta.matched` lists `keyword`, `semantic`
  or both.
- **Every result's thread** is always included, because a post or
  decision without its thread has no context. It is the one documented
  exception to `include` being opt-in (RFC 0002). `include` can add
  authors.

Search needs `threads:read`, the same as reading what it finds.

### What can be found

Search sees exactly what reading would, and is checked in the same
query, not afterwards:

- Only spaces the caller is a member of, or can see under
  `workspace` visibility, within an agent's space allow-list (RFC 0003).
  An admin's limited view of a private space (ADR 0025) finds nothing in
  it.
- Only published posts. Drafts are never searchable, the caller's own
  included.
- Not deleted posts. A tombstone has no text to match.
- Not check-in answers the caller cannot see yet (ADR 0043).
- Threads by their title and, once resolved, their outcome; decisions by
  their summary and rationale; posts by their body. Draft threads and
  draft decisions are never found.

Each searchable document and passage carries its workspace, space,
status and any embargo, so the visibility conditions are ordinary
indexed `WHERE` clauses, the same ones the read endpoints use, applied in
the same statement as the match.

### Keyword search

PostgreSQL full-text search over Search's own `search_documents` table
(RFC 0013): one row per searchable thread, post and decision, with its
text, a generated `tsvector` column and a GIN index, kept up to date by
listeners in the same transaction as the change, so an edit changes what
matches in the same transaction. Search never reads another context's
tables.

- Queries go through `websearch_to_tsquery`, which understands what
  people type into search boxes: `"exact phrase"`, `-excluded`, and `or`.
  A query that parses to nothing (only stop words) is `400`
  `invalid-query-parameter` with `source.parameter` `filter[q]`.
- Text is stemmed with PostgreSQL's `english` configuration, so "queues"
  finds "queue".
- Ranking is `ts_rank_cd`, which rewards terms that appear close
  together, with a small boost for titles over bodies. Highlights come
  from `ts_headline` on the same configuration, converted to ranges.

In Laravel 13 the column is a `tsvector` column, and the match is
`whereFullText` on it with `['mode' => 'websearch', 'vector' => true]`,
which compiles to `websearch_to_tsquery` against the stored vector, so
there is no raw SQL outside the ranking.

### Semantic search

Embeddings stored in pgvector, generated with the Laravel AI SDK
(`laravel/ai`) and queried with Laravel 13's vector support.

**What is embedded.** Text is embedded in passages, so a long post is not
squeezed into one vector:

- a thread's title and purpose, and its outcome once resolved;
- a decision's summary and rationale;
- a post's body, split on paragraph boundaries into passages of about
  2,000 characters.

Each passage is a row in one `search_passages` table, with the
resource's type and ID, its workspace, space and status for filtering,
the passage text, the embedding in a `vector` column, and the name of the
model that made it. The table has an HNSW index with cosine distance
(`vectorIndex`), and the migration calls `ensureVectorExtensionExists`.

**When.** Embedding happens in a queued job after a post is published or
edited, a thread resolved, or a decision published, so writing is never
slowed by a model call. Until its job has run, something is findable by
keyword but not yet by meaning; that is usually a few seconds.

**Never stale.** When a post is edited or deleted, a decision superseded
(it stays findable, marked superseded) or a space's visibility changes,
the affected passages are removed or updated in the same transaction as
the change, before any new embedding is generated. Old text is never
found by meaning after it has gone.

**Querying.** The query is embedded with the same model, cached so a
repeated query costs nothing (`toEmbeddings(cache: true)`), and matched
with `whereVectorSimilarTo` under a minimum similarity, set in
configuration and starting at 0.4. Results are the resources owning the
best passages, each resource once, scored by its best passage.

**Filtering before ranking.** The visibility conditions sit in the same
query as the vector match. A plain HNSW scan finds the nearest
neighbours first and filters them afterwards, which in a workspace where
the caller sees one space in fifty would return almost nothing. pgvector
0.8's iterative index scans (`hnsw.iterative_scan = relaxed_order`) keep
scanning until enough visible rows are found, so the filter is applied
inside the search rather than after it. That needs pgvector 0.8 or later.

**The model** is deployment configuration, like the built-in brief
generator's (RFC 0007), and the passage's `model` column records which
model made each vector. Changing the model means re-embedding: a command
re-embeds every passage in the background, and semantic search uses only
passages from the current model, so results are never a mix of two
incompatible vector spaces.

**On by default.** Semantic search is on for every new workspace, since
finding things by meaning is most of what makes search useful here. It
sends post content to the embedding provider, so an owner can turn it
off with the workspace's `semantic_search` setting, as action
`workspace.update`. With it off, nothing in that workspace is embedded,
existing passages are deleted, the default mode becomes `keyword`, and
`filter[mode]=semantic` or `hybrid` is `400` `invalid-query-parameter`,
with a `detail` saying why. Turning it back on embeds the workspace's
content again in the background.

### Hybrid search

Keyword search is precise and literal; semantic search finds meaning and
misses exact names, codes and IDs. Hybrid search runs both and combines
them, and is the default whenever semantic search is on.

- Both searches run with the same filters and visibility conditions,
  each producing its top 100 results.
- They are combined by reciprocal rank fusion: each result scores
  `1 / (60 + rank)` in each list it appears in, summed. A result near the
  top of both lists beats one at the top of only one, and neither
  search's raw scores, which are not comparable, are used.
- Each resource appears once, with its fused `score`.
- If either search has nothing to offer (a query that is only a product
  code, a query with no keyword match at all), the other's order stands.

Fusion happens in the Action, over two ordinary queries, so it needs
nothing from PostgreSQL beyond what keyword and semantic search already
use.

### Limits

Keyword search falls under the general rate limits. Semantic and hybrid
search cost a model call per new query, so they are also limited to 30
queries per minute per member, together, `429` `rate-limit-exceeded`.

### PostgreSQL, everywhere

Search makes PostgreSQL a hard requirement, as RFC 0001 assumed: generated
`tsvector` columns, `websearch_to_tsquery` and pgvector have no SQLite
equivalent. The test suite therefore runs against PostgreSQL with
pgvector, not in-memory SQLite, so the queries that enforce visibility
are tested on the database that runs them. Embeddings in tests come from
a fake provider returning deterministic vectors, so no test calls a
model. RFC 0013 covers how the database is provided.

### Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/v1/search` | Search threads, posts and decisions |

The MCP `search` tool (RFC 0011) calls the same Action with the same
filters.

## Alternatives considered

- **A search service (Meilisearch, Typesense, Elasticsearch).** Better
  relevance tuning out of the box, and a second store whose permissions
  have to be kept in step with the database, which is exactly where
  results leak.
- **Keyword as the default mode.** The spec's choice, and it misses
  everything not phrased the way it was written.
- **Semantic search off until an owner turns it on.** Safer for a
  workspace that has not thought about embedding providers, and most
  would never find the setting.
- **A separate vector database.** The same problem, for vectors.
- **Filtering after ranking.** Simple, and either leaks counts and
  highlights or returns near-empty pages.
- **One vector per post.** Long posts are summarised into mush, and a
  matching paragraph is lost in the rest.
- **Embedding synchronously on write.** Every post waits on a model call.
- **Laravel Scout.** A good fit for the search-service route above, and
  more abstraction than PostgreSQL's own features need here.

## Decisions this records

- **Search runs on PostgreSQL alone:** full-text search for keywords,
  pgvector for meaning, no external search or vector service.
- **Visibility is applied in the same query as the match,** using
  pgvector's iterative index scans for semantic search.
- **Embeddings are passages, generated in queued jobs with the Laravel AI
  SDK,** and removed in the same transaction as the change that makes
  them wrong.
- **Semantic search only uses vectors from the current model.**
- **Hybrid search, by reciprocal rank fusion, is the default mode,** and
  semantic search is on by default, with owners able to turn it off.
- **Keyword search stems English only in v1.**
- **Highlights are plain text with ranges,** never HTML.
- **Drafts are never searchable.**
- **The test suite runs on PostgreSQL with pgvector.**

## Open questions

None. Resolved in review on 2026-10-09:

1. **Semantic search** is on by default, and owners can turn it off.
2. **Hybrid search** is added, and is the default mode.
3. **English** stemming is enough for v1.
