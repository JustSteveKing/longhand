# ADR 0067: Search owns its projections, kept in step in the same transaction as each change

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0013

## Context

Search finds threads, posts and decisions, which belong to Conversations and Commitments. Reading their tables would make Search depend on two other contexts' schemas (ADR 0064). RFC 0009 also requires an edit to change what matches in the same transaction.

## Decision

Search keeps its own `search_documents` table, one row per searchable resource with its text, a generated `tsvector`, a GIN index and the visibility data search needs, and its `search_passages` table of embeddings. In-transaction listeners on the owning contexts' domain events update documents and remove passages; embeddings are generated after commit (ADR 0047). This amends RFC 0009, which put `tsvector` columns on the source tables; the behaviour is unchanged.

## Consequences

- Search reads only its own tables.
- Searchable text is stored twice, once where it belongs and once where it is searched.
- A missed listener would let a projection drift, so projections are tested against the source on every write path.

## Alternatives

- **`tsvector` columns on source tables.** RFC 0009's first design, and a context reading others' tables.
