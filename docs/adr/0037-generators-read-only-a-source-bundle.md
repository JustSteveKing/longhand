# ADR 0037: A brief generator reads only a source bundle built with the reader's permissions

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0007

## Context

A brief may only draw on what its reader can see. The generator is an agent with its own scopes and space allow-list, which are not the reader's. Reading with its own permissions builds the brief from the wrong view; reading with the reader's lets it see everything the reader can, for as long as its token lasts.

## Decision

When a brief starts generating, Longhand fixes its source bundle: the threads, posts, requests and decisions inside the brief's scope, window and focus that the reader can see at that moment. Only the brief's assigned generator can read it, at `GET /v1/briefs/{brief}/sources`, and only while the brief is `generating`. The bundle grants no other access, and every read is audited. Every citation in the submitted brief must be in the bundle, and a submission is accepted whole or rejected whole.

## Consequences

- No generator, built in or brought, can see more than one brief needs.
- The citation rule is one check against one list, covering existence, scope and visibility together.
- The bundle is a snapshot, so a post published mid-generation is not in the brief.

## Alternatives

- **Generator's own permissions.** The wrong view, or a generator with access to everything.
- **A token with the reader's permissions.** Far more than one brief needs.
- **Accepting the valid items and dropping the rest.** Gaps the reader cannot see.
