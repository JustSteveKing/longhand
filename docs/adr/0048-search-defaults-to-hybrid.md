# ADR 0048: Search defaults to hybrid ranking by reciprocal rank fusion, with semantic search on by default

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0009

## Context

Keyword search is precise and misses anything phrased differently; semantic search finds meaning and misses exact names, codes and IDs. Their scores are on different scales and cannot be compared directly.

## Decision

Search has three modes, `hybrid`, `keyword` and `semantic`, and `hybrid` is the default. Hybrid runs both searches with the same filters, takes the top 100 of each, and combines them by reciprocal rank fusion, scoring each result `1 / (60 + rank)` per list and summing. Semantic search is on for every new workspace; an owner can turn it off with `semantic_search`, which deletes the workspace's passages and makes `keyword` the default.

## Consequences

- People get good results without choosing a mode.
- Every default search costs an embedding of the query, cached for repeats, and counts against the semantic rate limit.
- Workspace content is sent to the embedding provider unless an owner turns it off.

## Alternatives

- **Keyword by default.** The spec's choice, and blind to meaning.
- **Weighted score blending.** Needs comparable scores, which the two searches do not have.
- **Semantic off by default.** Most workspaces would never turn it on.
