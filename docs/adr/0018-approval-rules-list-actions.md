# ADR 0018: Agent approval rules list actions, not scopes

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0003

## Context

The spec's `requires_approval_for` mixed scopes with things that were not scopes, such as `posts:publish`. A scope grants a family of actions, so approving one is either too coarse or needs exceptions.

## Decision

`requires_approval_for` lists action names from ADR 0017. When an agent attempts a listed action, Longhand creates the pending object as a draft, answers `403` `approval-required`, and puts a `draft_awaiting_approval` item in the owner's inbox.

## Consequences

- Approval is as precise as the actions are.
- Approval rules, audit entries and errors share one vocabulary.

## Alternatives

- **Rules keyed by scope.** The spec's starting point, and too coarse.
