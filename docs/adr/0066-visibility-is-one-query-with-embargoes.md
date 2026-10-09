# ADR 0066: Visibility is one query owned by Conversations, and other contexts' rules reach it as embargoes

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0013

## Context

Who can see a space, thread or post is checked by pages, the API, MCP, brief bundles, search and event rendering. Each having its own version is how something leaks. And check-ins hide posts from some members (ADR 0043), while posts belong to Conversations, which must not depend on CheckIns.

## Decision

Conversations owns one public `Visibility` query, built as query constraints so it applies inside the same SQL as any read, and every read path uses it. Rules from other contexts reach it as data: a post can carry an embargo, holding it from listed members until each is released. CheckIns places and lifts embargoes through a public Conversations Action.

## Consequences

- One place to change who can see what, and one place to test it.
- Conversations supports a general mechanism without knowing why it is used.
- Every new reader of posts gets visibility by using the query, not by reimplementing it.

## Alternatives

- **Visibility checks on loaded models.** Too late for search and pagination.
- **Check-in rules inside Conversations.** A dependency in the wrong direction.
