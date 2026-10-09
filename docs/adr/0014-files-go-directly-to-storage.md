# ADR 0014: Files go directly to storage, never through the API

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0002

## Context

Posts carry attachments of up to 100 MiB. Uploading through the API would hold an application worker for the whole transfer and push every byte through PHP.

## Decision

`POST /v1/uploads` declares a file's name, type and size, checks them against the limits in RFC 0002, and returns an upload URL valid for 15 minutes. The client sends the bytes there directly and references the `upl_` ID from a post. The API never receives file bytes. A request document is limited to 1 MiB, a post body to 40,000 characters, and a post to 10 attachments.

## Consequences

- Upload size and duration have no effect on the API's workers.
- An upload is a two-step flow for clients, and an upload URL is a credential for 15 minutes.
- An abandoned upload leaves a file in storage with no post, which needs cleaning up.

## Alternatives

- **Multipart uploads to the API.** One request, and the API carries every byte.
