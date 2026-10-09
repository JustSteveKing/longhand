# ADR 0009: Unsupported query parameters, includes and sorts are 400

- **Status:** Accepted
- **Date:** 2026-10-08
- **From:** RFC 0002

## Context

Many APIs ignore query parameters they do not recognise. A misspelt filter then returns the wrong data with a `200`, which looks like success. JSON:API requires `400 Bad Request` for unrecognised parameters, unsupported `include` paths and unsupported sort fields.

## Decision

Any query parameter Longhand does not support, including `fields[...]` while sparse fieldsets are unsupported, an unsupported `include` path or sort field, and an invalid filter value, is `400` `invalid-query-parameter`, with `source.parameter` naming it. Longhand-specific parameter names contain a character outside `a-z`, as JSON:API requires, so they cannot collide with future JSON:API parameters.

## Consequences

- A typo is an error, not a silently wrong result.
- Adding support for a parameter later is not breaking, since it only turns an error into a success.
- apiguide.dev needed an `invalid-query-parameter` page, because its `unprocessable-query` is `422`.
- The spec's `?q=` for search becomes `filter[q]`.

## Alternatives

- **Ignore unknown parameters.** Forgiving, and it hides bugs.
- **`422` for query problems.** Defensible, but JSON:API requires `400`.
