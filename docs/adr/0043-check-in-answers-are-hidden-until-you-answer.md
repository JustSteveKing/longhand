# ADR 0043: In a check-in, a person sees others' answers only once they have answered

- **Status:** Accepted
- **Date:** 2026-10-09
- **From:** RFC 0008

## Context

When people can read everyone else's standup before writing their own, the first answer sets the tone, and later ones drift towards it. Async standups are most useful when each person writes their own view first.

## Decision

Until a person asked in a run has answered, said there is nothing to report, or reached the end of their window, the run's thread hides other people's answers and the replies to them. They see its title and counts. Posts that need them, a mention or a blocker routed to them, are visible at once. Hidden posts are `404` to them and left out of their briefs' source bundles. People who were not asked, and anyone excused, see everything.

## Consequences

- Answers are independent, which makes the standup more honest.
- Visibility of a post now depends on the reader's state in a run, as well as their access to the space; the check sits with every other visibility rule.
- Someone who wants to coordinate with a colleague's answer has to answer first.

## Alternatives

- **Everything visible from the start.** Easier coordination, and anchored answers.
- **Hiding blockers too.** A blocker that needs someone would wait for their own standup.
