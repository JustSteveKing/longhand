# ADR 0052: Webhook subscriptions deliver as a member, and members create them subject to approval

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0010

## Context

Integrations and agents need events by webhook, but agents cannot hold `webhooks:write` (RFC 0003), and a webhook sends workspace content to an address outside Longhand. Slack's model lets any member install an app that sees only what its installer or bot can see, and lets admins require approval.

## Decision

Every subscription has a `delivers_as` member, whose visibility it receives: for a member, themselves or an agent they own; for an owner or admin, any member. Any non-guest member can create one. A member's subscription starts `pending` until an owner or admin approves it, unless an owner turns the workspace's `subscription_approval` setting off. Owners and admins can see, disable and delete every subscription. Webhook URLs must be `https` and are checked against private, loopback and link-local addresses at every delivery, and redirects are not followed.

## Consequences

- Agents receive events without being able to point subscriptions anywhere themselves.
- Personal integrations are possible, and none sends anything before an owner or admin has seen it, by default.
- A DNS change cannot turn a subscription into a request inside the network.

## Alternatives

- **Owners and admins only.** No personal integrations.
- **No approval by default.** Every member a route for content to leave.
- **Agents holding `webhooks:write`.** Agents choosing where workspace data goes.
