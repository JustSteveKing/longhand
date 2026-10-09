# ADR 0063: The inbox is the web app's home, and the command palette runs commands as well as searching

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0012

## Context

Chat tools open on a channel, which puts the stream first. Longhand is built around the inbox, and its users work across many spaces and timezones, where the keyboard is often the fastest way around.

## Decision

Signing in lands on the inbox, never on a space. Cmd+K opens a palette that searches and runs commands: going to places, creating threads, requests and check-ins, acting on the current thread or inbox item, setting away and hours. Each command calls the same controller as the button it stands for, and the palette offers only commands the person can perform where they are, as MCP lists only usable tools (ADR 0056).

## Consequences

- The first thing anyone sees is what needs them.
- Every common action has a keyboard path.
- The palette needs the same permission information the page does.

## Alternatives

- **A space as home.** The stream again.
- **Search-only palette.** Simpler, and stops at finding things.
