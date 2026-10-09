# ADR 0070: Briefs and embeddings go through the Laravel AI SDK, with Anthropic for briefs and Voyage AI for embeddings

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0013

## Context

Longhand calls models for two jobs: generating briefs (RFC 0007) and embedding text for search (RFC 0009). The Laravel AI SDK handles both, with providers chosen by configuration. Anthropic offers no embeddings model.

## Decision

Both jobs use `laravel/ai`. By default the built-in brief generator uses Anthropic (Claude) and embeddings use Voyage AI, each set in `config/ai.php` and swappable without code changes. Tests use the SDK's fakes, including `Embeddings::fake()`, so no test calls a model. Which model makes each passage is recorded with it (ADR 0047).

## Consequences

- One SDK, one configuration file, two providers.
- Changing the embeddings provider means re-embedding, which ADR 0047 already handles.
- A deployment needs keys for two providers, unless it chooses one that does both.

## Alternatives

- **One provider for both.** Simpler, and Anthropic cannot do embeddings.
- **Calling provider APIs directly.** More code, and no fakes.
