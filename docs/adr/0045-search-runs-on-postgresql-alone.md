# ADR 0045: Search runs on PostgreSQL alone, with full-text search and pgvector

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0009

## Context

Search has to find things by their words and by their meaning, and must never return what the caller cannot see. A search service or vector database would hold a second copy of the content, whose permissions would have to be kept in step with the database's, which is where results leak.

## Decision

Keyword search uses PostgreSQL full-text search: a generated `tsvector` column per searchable table with a GIN index, queried through `websearch_to_tsquery` with English stemming, via Laravel's `whereFullText`. Semantic search uses pgvector: passage embeddings in a `vector` column with an HNSW cosine index, queried with Laravel 13's `whereVectorSimilarTo`, and generated with the Laravel AI SDK. No external search or vector service is used.

## Consequences

- One store, one set of permissions, one transaction for a change and its index.
- PostgreSQL with pgvector 0.8 or later is a hard requirement.
- Relevance tuning is ours to do, with fewer knobs than a dedicated engine has.

## Alternatives

- **Meilisearch, Typesense or Elasticsearch, through Scout.** Better tuning out of the box, and a second store to keep permissions in step with.
- **A dedicated vector database.** The same problem for vectors.
