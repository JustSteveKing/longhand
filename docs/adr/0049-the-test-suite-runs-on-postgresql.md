# ADR 0049: The test suite runs on PostgreSQL with pgvector

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0009

## Context

The starter kit runs tests on in-memory SQLite. Search depends on generated `tsvector` columns, `websearch_to_tsquery` and pgvector, none of which SQLite has, and the queries that enforce visibility in search are the ones most worth testing.

## Decision

The test suite runs against PostgreSQL with the pgvector extension, the same database the application runs on. Tests that need embeddings use a fake embedding provider that returns deterministic vectors, so no test calls a model. RFC 0013 sets out how the database is provided locally and in CI.

## Consequences

- What is tested is what runs.
- Tests need a PostgreSQL service, locally and in CI, and run slower than on in-memory SQLite.

## Alternatives

- **SQLite for most tests and PostgreSQL for search.** Two databases to reason about, and visibility tested on one and run on the other.
