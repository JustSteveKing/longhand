# ADR 0025: Admins see that a private space exists and who is in it, never what is in it

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0004

## Context

A private space should be private from colleagues, admins included. But a workspace's admins need to know what spaces exist, and someone has to be able to rescue a space whose owner has left.

## Decision

To an admin or owner who is not a member, a private team or project space appears with its name, kind, owner, member count and members, with `meta.access: "limited"` and no description. Its threads, posts and everything under them are `404` (ADR 0012). Admins can transfer, archive and remove members from it, but cannot join it or add anyone. Direct spaces are not shown to admins at all.

## Consequences

- `private` means what people expect when they choose it.
- No space is stranded when its owner leaves.
- Compliance needs, such as exporting a private space for a legal hold, are left to the retention RFC.

## Alternatives

- **Invisible to admins.** Leaves spaces nobody can manage.
- **Readable by admins.** Makes `private` mean less than it says.
