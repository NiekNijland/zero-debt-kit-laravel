# Templates: `backlog.md` and `CONTINUE.md`

Two files. Split them when you create them in the project.

---

# <TICKET> code cleanup: backlog (<project>)

Marks: ✅ merged · 🔵 in an open PR · ⬜ open · ⛔ closed without change (one-line reason). Ids by area: N naming and HTTP, E errors and logging, C config and bootstrap, D database, F frontend, T tests, S structure, U duplicated logic, X dead code, K checks, G guards, Z zero-debt counters.

## Items

| Id | Mark | What is wrong today | Count | Command | The one way | Step | PR |
| --- | --- | --- | ---: | --- | --- | --- | --- |
| S1 | ⬜ | | | | | | |

Every dashboard counter is also a row here (id `Z-nn`), so the backlog is empty only at zero. This table is the only place an item is mapped to a step and a PR.

## Decision lists

A rule the code breaks in several places where each place needs its own answer (kit `04-guards.md` C7, point 5). The guard for the part every site meets is built at once; the full guard lands in the PR that brings the count to zero. Every site is answered before the program closes. One block per rule.

**<rule, one sentence>** · count <n> of <total> · command: `<command>` · the part every site already meets, and its guard: <guard>

| Site | What it does today | The question | Answer, with the evidence | Answered by (lead; owner when behavior changes) |
| --- | --- | --- | --- | --- |
| `<file or name>` | | | | |

## PR table

| PR | Branch | Area | Theme | Items | State (CI, approvals, threads, conflicts) | Merged |
| --- | --- | --- | --- | --- | --- | --- |

## Hazards found in this project

-

## Open loops

- <what, who, the "start when" signal>
- <candidate guards that wait for one decision before they can be counted (kit `04-guards.md` C7): the candidate, the decision, who>

## After-zero backlog

- <audit findings of a kind outside the signed definition, with their count (kit `06-lock-and-audit.md` E2)>

## Updates

- <one line per merge wave: what merged, what changed, new counts>

---

# <TICKET> code cleanup: how to continue

Read this first, then the kit's `07-process.md`, then the last update in `backlog.md`.

## What this is

Repository, stack, goal in two lines, the ticket.

## Files

1. `backlog.md` 2. `zero-debt-plan.md` 3. `project-profile.md` 4. the kit at `<path>`.

## Standing rules from the owner

- <the non-negotiables of this project>

## State (<base branch> at <commit>)

- Open PRs: id, branch, head, CI, approvals, threads, what each needs.
- Worktrees with uncommitted or unpushed work, and whether that work is verified.
- What ran locally and what did not. Expected CI risks.

## Read for the current step

- Step <n>: its part of the kit's `05-program.md` D2, and the catalog tables it names: <files and table names>.

## Next steps, in order

1.

## What the owner must do by hand

-

## Open questions for the owner

-

## Lessons from the last wave

-
