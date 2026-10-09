# ADR 0046: Search applies visibility in the same query as the match, never afterwards

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0009

## Context

Filtering results after ranking leaks: counts, highlights and scores can reveal what the caller cannot open. For vectors it also fails quietly, because an HNSW index returns the nearest neighbours first, and if the caller can see one space in fifty, filtering them afterwards leaves almost nothing.

## Decision

Every searchable row and passage carries its workspace, space and status, and the visibility conditions the read endpoints use are `WHERE` clauses in the same statement as the match. For semantic search, PostgreSQL runs with pgvector's iterative index scans (`hnsw.iterative_scan = relaxed_order`), so the index keeps scanning until it has enough visible rows. Drafts, tombstones, check-in answers the caller cannot see yet, and private spaces they are not in are never matched.

## Consequences

- Nothing about an invisible resource can appear in a result, a score or a count.
- Visibility rules have one more consumer, so a new rule must be added to the search query too; the tests cover both together.
- Denormalised space and status columns are kept in step in the same transaction as the change that alters them.

## Alternatives

- **Filtering after ranking.** Leaks, and returns near-empty pages.
- **Per-space indexes.** Workable for a few spaces and unmanageable for many.
