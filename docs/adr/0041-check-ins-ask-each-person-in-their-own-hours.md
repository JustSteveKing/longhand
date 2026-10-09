# ADR 0041: Check-ins ask each person at their own local time, and never interrupt

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0008

## Context

A synchronous standup always lands badly for someone on a distributed team. An async one only helps if the prompt itself respects each person's hours.

## Decision

By default a check-in asks each person at its scheduled time in their own timezone. Each prompt is a `check_in_due` inbox item whose delivery is worked out by the same function as every other delivery (ADR 0032), so a prompt outside someone's hours or in a focus block waits for their next working window. Each person has the full `close_after` window from when they were asked, and the run closes when the last window ends. People away for their whole window are excused. Each person gets one reminder, two working hours before their window closes. A `space` timing option asks everyone at one shared moment when a team wants that instead.

## Consequences

- Nobody is asked at night, and nobody gets less time because they are further west.
- A run spans the team's timezones, so it opens and closes at different local times for different people.

## Alternatives

- **One shared time by default.** Someone is always asked at the wrong hour.
- **A fixed close time for everyone.** People asked later get less time.
