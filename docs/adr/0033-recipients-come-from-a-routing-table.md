# ADR 0033: Inbox recipients come from a routing table of what happened, never from participation

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0006

## Context

Chat tools tell everyone in a conversation about everything in it, which is the stream Longhand exists to replace. The inbox is only useful if each item in it needs the person to act.

## Decision

Who gets an inbox item is decided by one table in RFC 0006, keyed on what happened: a question asked, a request assigned, a thread gone stale and so on, each with one reason and a defined recipient. Being a participant in a thread does not by itself deliver anything; participants hear about outcomes in their digest. The actor never receives an item about their own action, nobody receives one about a thread they cannot see, and a member has at most one open item per reason and subject. A new reason is added to the table by the RFC that introduces it.

## Consequences

- Every inbox item can say exactly why it is there.
- Busy threads do not fill the inboxes of people who are only watching.
- Adding a kind of notification means adding a row with a reason, which keeps it a deliberate choice.

## Alternatives

- **Notify all participants.** The stream again.
- **Per-member subscription rules.** Flexible, and it moves the work of protecting attention onto every member.
