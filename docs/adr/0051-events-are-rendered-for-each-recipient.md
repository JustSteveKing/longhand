# ADR 0051: Every event is rendered for its recipient, with their visibility when it is delivered

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0010

## Context

An event carries the full resource, so consumers never need a follow-up fetch. But several resources look different to different members: limited availability, private spaces to admins, check-in answers before answering, drafts. One rendering for everyone either over-shares or under-serves.

## Decision

An event's `data.resource` is the resource as its recipient would get it from the API, with `meta.access` where it applies, evaluated when the event is delivered, not when it happened. A recipient to whom the resource would be `404` gets no event. Webhooks render as the subscription's `delivers_as` member; the stream renders per topic, as ADR 0053 sets out.

## Consequences

- Events can never reveal more than the API would.
- Rendering happens per recipient at delivery, which costs more than rendering once.
- Access lost between the change and its delivery stops the delivery.

## Alternatives

- **Thin events.** A fetch per event for every consumer.
- **One rendering for all.** Leaks or loses information.
