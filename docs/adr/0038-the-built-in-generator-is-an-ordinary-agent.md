# ADR 0038: The built-in brief generator is an ordinary agent, with no path of its own

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0007

## Context

Teams can bring their own brief generator. If the built-in one uses a private shortcut, the public path is never exercised by the code that matters most, and the guarantees a brought generator lives under are only promises.

## Decision

Every workspace is created with an agent named `Longhand`, with `briefs:write` and nothing else, set as the workspace's `brief_generator`. It receives `brief.requested`, reads the source bundle and submits through atomic operations exactly as a brought generator does, through the same Actions and validation. Owners and admins can replace it. Because it is the workspace's choice rather than one person's, flags on its briefs go to every owner.

## Consequences

- The path a brought generator uses is the path every brief takes, so it is always tested.
- Replacing the generator is a setting, not a migration.
- Every owner hears about inaccurate built-in briefs.

## Alternatives

- **A built-in generator inside the domain.** Faster, and a second path with its own rules.
