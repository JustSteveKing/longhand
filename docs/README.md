# How Longhand is specified

Longhand is built spec first, in public. Every piece of behaviour exists as
a document before it exists as code, and the documents say why as well as
what. That is part of what Longhand teaches: the reasoning is as much the
lesson as the code.

## The documents

| Document | Answers | Lives in | Changes |
| --- | --- | --- | --- |
| **The spec** | What is the whole product, and how do its parts fit? | [`spec.md`](spec.md) | The parent document. Each part is extracted into RFCs and ADRs, then the spec points at them |
| **RFC** | What should we build, and how should it work? | `docs/rfc/` | Rewritten freely while Draft; once Accepted, changed only by a later RFC, and only until code exists |
| **ADR** | What did we decide, and what did it cost? | `docs/adr/` | Never edited after Accepted; superseded by a new ADR |
| **API contract** | What exactly does the API accept and return? | `api/openapi.yaml` | Written from accepted RFCs, before any handler exists |

## The order things happen in

1. **The spec sets the shape.** [`spec.md`](spec.md) is the whole product in
   one place: principles, conventions, the domain, every surface. It is a
   draft, and it is where everything below comes from.
2. **RFCs take a part each.** A section of the spec that needs its own
   design (the attention model, briefs, the stream) becomes an RFC with the
   problem, goals, non-goals, the design, the alternatives and the open
   questions. It starts as **Draft** and is reviewed before anything
   depends on it.
3. **Decisions become ADRs.** Every choice worth remembering on its own
   ("errors are RFC 9457", "the stream is SSE through Mercure") gets an ADR,
   so it can be found, cited and superseded without rereading the design.
   The spec's "Decided in this draft" table is the first batch.
4. **The API contract follows the RFC that defines it.** The OpenAPI
   document is written by hand from accepted RFCs and reviewed on its own.
5. **Code implements accepted documents.** A change with no accepted RFC or
   ADR behind it is not ready to build. If building shows a document was
   wrong, the document changes first: a new ADR, or a new RFC that
   supersedes the old one.

## Statuses

**RFCs:** `Draft` (being written and reviewed) · `Accepted` (agreed;
build to it) · `Implemented` (built and shipped) · `Rejected` (decided
against, kept for the record) · `Superseded by RFC NNNN`.

**ADRs:** `Proposed` · `Accepted` · `Superseded by ADR NNNN` ·
`Deprecated`. An accepted ADR is never edited except to set its status
when something supersedes it.

**Amending an accepted RFC.** Until code implements it, an accepted RFC
can be changed by a later RFC that needs it to change: a new field, a
new setting, an entry in an index, a decision reversed in review. The
change is made in the accepted RFC itself, so it stays the current truth,
and its header gains an `Amended by: RFC NNNN` line naming every RFC that
changed it. Writing the API contract can amend an accepted RFC the same
way, where the contract needs a name, a shape or a rule the RFC left
open; the header then says `API contract review, YYYY-MM-DD`. A reversed decision says so where it is written. Once code
implements an RFC, it is frozen, and changing it takes a new RFC that
supersedes it. ADRs are never amended this way; a changed decision is a
new ADR.

## Numbering and names

Four digits, never reused, in the order written:
`docs/rfc/0003-the-attention-model.md`,
`docs/adr/0007-the-stream-is-sse-through-mercure.md`. ADR titles state the
decision ("Errors are RFC 9457"), not the topic ("Errors"). Templates:
[RFC](rfc/0000-template.md), [ADR](adr/0000-template.md).

## Keeping the indexes honest

[`rfc/README.md`](rfc/README.md) and [`adr/README.md`](adr/README.md) list
every document with its status. A change to a status changes the index in
the same commit.
