# Templates for files in the repository

Seven files, and one guard-rows file per guard (section 5b). Worked examples of most are in `reference-project/` (`AGENTS.md`, `app/AGENTS.md`, `tests/AGENTS.md`, `resources/js/AGENTS.md`, `docs/review-rubric.md`, `docs/decisions/0002-one-backend-pattern.md`).

---

## 1. Root `AGENTS.md`: the sections the program adds

```markdown
## House rules

**<Area>**

- <rule, one line, with the guard that enforces it when there is one>

## One pattern

Every task has one way to do it. New code copies the example file, not the neighboring file. Reviewers check against `<path of the review rubric>`. One command runs what CI's checks step runs: `bash scripts/checks.sh`.

| Concern | The single way | Copy this |
| --- | --- | --- |
| Where a class lives | the folder table in `<backend AGENTS.md>`; `<layout test>` fails on any other place | `<path>` |
| <concern> | <the way; what is never done; the guard> | `<a real file that passes every rule in this table>` |

- A guard (architecture rule, list, baseline, suppression) only shrinks. Never add an entry to make a build green: fix the code, or ask <owner>.
- A PR that converges a pattern adds the guard that stops it coming back, and a row here.

## Scoped guidance

More specific `AGENTS.md` files under `<paths>` add instructions for those areas.
```

A row that has no example yet says "lands with <PR>" in the Copy column until that PR merges, and nowhere else in the guides (`03-conventions.md` B2).

---

## 2. Backend `AGENTS.md`

```markdown
# BACKEND

Every task has one way to do it, written down here. Code that does it differently is waiting to be moved: copy this file, not the neighboring code. `<layout test>` fails on any class that leaves the layout below. The reasons are in `<decision record>`.

## LAYOUT

| Folder | Holds |
| --- | --- |
| <framework folders, flat, like the stock framework> | |

Domain code lives in one module per domain, `<the kit's module root>/<Domain>`, using only these folders:

| Folder | Holds | Name |
| --- | --- | --- |
| <the kit's folder set, from the catalog's "Structure"> | | |

<Which layers are pure, and the one sanctioned crossing.>
<What moving a class does to stored names: the register, and the three releases.>

## ONE WAY PER TASK

- **<Task>** <the way>. Never <the old ways>; `<guard>` fails on them. <What the guard cannot decide>: review checks that, the guard does not.
```

---

## 3. Frontend `AGENTS.md`

```markdown
# FRONTEND

Every task has one way to do it, written down here: copy this file, not the neighboring code.

## LAYERS

| Layer (folder) | Holds | May import |
| --- | --- | --- |
| <the kit's layer table, from the catalog's "Layers", lowest layer first> | | |

## FILES AND COMPONENTS

<File names; the one component form; the limits; where a test lives.>

## ONE WAY PER TASK

- **<Task: write, read, form, poll, storage, dates, navigation ...>** <the one hook or helper, with its file>. Never <the old ways>; `<lint rule>` fails on them.
```

---

## 4. Tests `AGENTS.md`

```markdown
# TESTS

## TIERS

| Tier | Set-up it may use | Lives in |
| --- | --- | --- |
| <pure unit, unit with light set-up, feature, feature with tenant context> | | |

## ONE WAY PER TASK

- **Where a test lives** <the rule>; `<guard>` fails on any other place.
- **Helpers, fixtures, fakes** <the rule and the support folder>.
- **Config, clock, container** <the one spelling each>.
- **Spelling** <the project's test framework and its column of "One spelling in a test">.
- **Coverage** the floor is `<number>` in `<committed file>`, on its way to the kit's goal of 95 or at it; the `min` ratchet row `coverage-floor` holds it. The mutation score is `<number>` in `scripts/mutation-floor.txt`; the row `mutation-floor` holds it.
```

---

## 5. Decision record

```markdown
# One <area> pattern

Status: accepted.

## Context
<What existed side by side, with counts. Why that keeps getting worse: people and agents copy the code next to the code they change.>

## Decision
<The layout and the rules in a paragraph each; where the detail lives.>
<How it is enforced, and that the list of existing violations only shrinks.>

## Consequences
<What moves, in what size of PR. What a move does to stored names and how that is handled.>
```

---

## 5b. Guard rows (`docs/guards/<guard>.md`)

One short file per guard. It holds the guide rows and tables that the guard reads, so no other guard edits those lines, and the one-pattern table and the rubric link to it by the guard's name.

```markdown
# Guard: <guard name from the list in the root AGENTS.md>

<One sentence: what the guard decides.>

| Rule id | The guide must say (the words the guard pins) | Instead |
| --- | --- | --- |
| <id> | <words> | <what to do instead> |

<Tables the guard reads (layout, verbs), in the form it parses.>
Review checks, and the guard does not: <the judgement, with its rubric item>.
```

---

## 6. Review rubric

The numbers are the kit's and are the same in every project: the catalogs cite them as "rubric item N". A project adds its own items after the last number.

```markdown
# Review rubric

What a reviewer, human or agent, checks in every pull request. The one-pattern table in the root `AGENTS.md` is the reference: when the PR does a task differently from that table, it is a finding, even when CI is green.

## Findings

Blockers

1. The PR adds an entry to an allowlist, baseline, suppression, skip, ignore or reasoned-exception list. These lists only shrink.
2. The PR converges a pattern without the guard that keeps it, removes a guard, makes a check non-blocking, scopes it to changed files, or hides its failure. Every diff to the ratchet script outside adding a row, to the other scripts in `scripts/`, and to the pipeline files is read line by line, with the reason of its `[ratchet: script]` tag.
3. Object-level authorization: an id from any entry point (a route, a component action, a console command, a job payload) reaches a record without binding and a policy; an existence rule on a table name; a raw table read; a scope bypass on tenant data.
4. A moved or renamed class whose name is stored in data, outside the three releases; a changed or removed line in the register of stored names without its alias (the guard fails that) or without its data migration (the guard cannot see that: you check it).
5. A migration that builds an index or a foreign key on an existing table without the engine's non-blocking form.
6. A commit with `[ratchet: <name>] <reason>` or `[ratchet: script] <reason>`: read the reason and the diff. A "new row" in the ratchet output that does not belong to a guard this PR installs.
7. Production behaves differently after the PR and the description does not say so under "Behavior that changes on purpose": a fix a test on the real path exposed, a corrected lookup key.
8. A raised cap in the caps file without its `[ratchet: cap-<key>]` tag, or with a reason that does not hold (item 6); a test that switches off middleware or exception handling; a new double of a class of the project where the real class or a fake works.

Warnings

9. New code does a task differently from the one-pattern table, in a part no guard decides.
10. Authorization by role name, or a check in the wrong layer.
11. New behavior that no test names; a test of the break sample that stayed green.
12. The PR changes a counter and the description does not say which and by how much.
13. A new or changed guard without a failing example for each other spelling of its construct: through a parent class or a sub-interface, as a function and as a method, under an alias or a full name.
14. A sentence in a guide that says a guard fails on something the guard's failing examples do not show; a sentence that describes a transition; a finished plan that is not archived.
15. A commit type that does not fit the change.
16. <project copy and UI rules>

Judgements (review only: no guard can decide these)

17. Frontend design: state with phases is a pure reducer over a tagged union with its own test; a context is split by what changes together.
18. A test exercises the class it names, and the class it names is the one whose behavior it asserts.
19. A value that depends on runtime configuration fits: the timeout of a queued class stays under its worker's timeout; the lock of a scheduled task expires at most at its interval.
20. A migration's `down()` really undoes its `up()`; a type change on a big table has its migration plan in the PR.
21. The empty value of a checked helper (a failed parse, an unexpected type) is handled by its caller; an overriding property carries the same intent as an overriding method.
22. The reason of a `[ratchet: comments]` override meets the comment rule: a future reader would act differently because of the comment.

Suggestions

23. A comment that restates the code; a new abstraction where deleting code would do.

## What CI already enforces, and what is review only

| Row | Guard | Review only |
| --- | --- | --- |
| <every row of the one-pattern table, same first column> | <test, lint rule or script: only what its failing examples show> | <the number above, and what the reviewer checks by eye> |

## PR checklist

Lives in `<PR template path>`. PRs opened through an API may not get it, so agents copy it into the description.
```

---

## 7. PR template (lands in step 2; the pattern lines join in step 3)

```markdown
## What and why


## Checklist

- [ ] Ticket and type in the title and every commit
- [ ] Follows the one-pattern table in AGENTS.md; copied the example file
- [ ] No entry added to a list, baseline or suppression
- [ ] If this PR converges a pattern: the guard that keeps it is in this PR
- [ ] Moved or renamed classes: the register, the release this PR is of the three, generated types
- [ ] New entry point that takes a record id: its check, and it is in the coverage test
- [ ] New route: permission or policy on the route, rate limit
- [ ] New migration: the non-blocking form on existing tables
- [ ] No comments; only what a future agent would act on
- [ ] A new class whose name is stored in data: its line in the register
- [ ] Behavior that changes on purpose is named in the description, or "None"
- [ ] Which counter does this change, and by how much? (the dashboard, `--offline`)
- [ ] <project copy, UI and design rules>
```
