# ADR 0023: Space memberships and reactions are resources, not relationships or sub-paths

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0004

## Context

The spec added members to spaces at `/spaces/{space}/members/{member}` and reacted at `/posts/{post}/reactions/{emoji}`, neither of which is JSON:API. A to-many relationship would be JSON:API, but cannot be paginated sensibly, has nowhere to record who added whom, and would need `If-Match` on the whole parent.

## Decision

`space_memberships` (`smb_`) and `reactions` (`rxn_`) are resources. Joining, adding, leaving and removing are creates and deletes of memberships; reacting and un-reacting are creates and deletes of reactions. A membership carries `muted`, so it has an `ETag` and its `PATCH` and `DELETE` take `If-Match`. A reaction is never edited, so it has no `ETag`, and its `DELETE` takes none.

## Consequences

- A space's members are listed and paginated like any collection.
- Who added a member is recorded on the membership.
- Per-member settings for a space, starting with muting, have a natural home.

## Alternatives

- **To-many relationships.** JSON:API's built-in shape, and the wrong one for a membership with data of its own.
- **The spec's sub-paths.** Not JSON:API.
