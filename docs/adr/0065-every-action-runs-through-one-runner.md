# ADR 0065: Every Action runs through one runner that checks, audits and records events in one transaction

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0013

## Context

Permission checks, approval rules, the audit log and the outbox must apply to every use case on every surface (ADR 0017, ADR 0021, ADR 0050). Left to each controller or tool, one will forget.

## Decision

Each use case is one Action, declared with its action name and scope. Actions are invoked only through the Action runner, which opens a transaction, checks scopes, role, agent rules, visibility and the action's policy, takes the draft path when an approval rule applies, runs the Action, writes the audit entry and the outbox rows, and commits. A refused call is audited after the rollback. An Action's `handle` takes an `AuthorisedActor` that only the runner can construct, so an Action called directly does not type-check. Lifecycles are enforced by model methods; nothing sets a status directly.

## Consequences

- No surface can skip a check, an audit entry or an event.
- Controllers, tools and commands are thin adapters that build a payload and call the runner.
- The type system, not review, keeps Actions behind the runner.

## Alternatives

- **Policies called from controllers.** Laravel's default, and one forgotten call from an unchecked surface.
- **Middleware per surface.** Three implementations of one rule.
