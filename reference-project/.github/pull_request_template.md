## What and why



## Checklist

- [ ] Ticket in the title and every commit (PROJ-xxxx or fix(PROJ-xxxx):, PROJ-0 without a ticket)
- [ ] Follows the one-pattern table in AGENTS.md; copied the example file
- [ ] No allowlist, baseline, suppression or ignoring() entry added
- [ ] If this PR converges a pattern: the guard that keeps it is in this PR
- [ ] Moved or renamed classes: events.name, notifications.type, queued jobs, generated.ts handled
- [ ] Every new string in every supported language
- [ ] New route: permission on the route, ->can('ability', 'model') for a model in the URL, throttle category
- [ ] New migration: indexes CONCURRENTLY on existing tables, foreign keys indexed
- [ ] No comments by default: only a non-obvious constraint, invariant, ordering or locking rule, external quirk, or security or privacy reason; never restate the code
- [ ] Immutable date casts
- [ ] Which counter does this change, and by how much? (`bash scripts/debt-dashboard.sh --offline`)
- [ ] UI: the design is linked
- [ ] UI: every deviation from the design is named with its reason, and is recorded in the design file
- [ ] UI: components from the table in DESIGN.md; every new variant or state has a story
