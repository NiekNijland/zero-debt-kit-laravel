# Zero technical debt: burn-down plan (<project>)

Goal: every part of the codebase clean, every ratchet, list, baseline and suppression at 0, and no way back.

Measured against `<branch>` `<commit>`. Nothing in the repository was changed to measure. Every number comes from the command in the appendix with the same id.

Not measured, and why:

- <item>: <reason>

## 1. Dashboard

### 1a. Ratchet counters

| Counter | Now | Target | Meaning of the target | Guard |
| --- | ---: | ---: | --- | --- |
| | | 0 | | ratchet row |

Shrink-only rows (kit `01-principles.md`, "Shrink-only rows"): the three fixed ones, and by decision row: <row or `cap-<key>`, decision id, the evidence why zero is impossible, or the owner's pin of a major version>.

### 1b. Other debt

| Id | Debt kind | Where | Now | Target | Guard (exists / add) |
| --- | --- | --- | ---: | ---: | --- |
| M-01 | | | | 0 | |

Rows with a cap (the caps file; a cap only goes down and is lowered to its row at the end of every step; a cap that is not 0 at the end needs a decision row, and its `cap-<key>` is then in `SHRINK_ONLY`):

| Key | Now | Cap | Zero when | Decision row, if it stays above 0 |
| --- | ---: | ---: | --- | --- |

### 1c. Re-measured at <commit>

| Counter | Before | Now | Target | Status |
| --- | ---: | ---: | ---: | --- |

## 2. The catalog mapped onto this project

- Version floor: per platform part, installed against floor, and the owner's answer.
- Domains and modules: <domain> = <today's folders>.
- Per catalog row: the ways found today and their counts (or a pointer to the backlog ids).
- Tasks the catalog does not cover, with the way chosen and why (kit `03-conventions.md` B1).
- Rows that apply in another technique's form, with the project's mechanism.
- Exceptions the owner granted (decision row ids).

## 3. What blocks what

- <dependency, one line each; see kit `05-program.md` D1 and add this project's own>

## 4. Definition of clean (runnable checks)

Signed by the owner: <name>, at <commit>. Rows are only added; a redefinition is a decision row with the number under both definitions.

Backend
- <file that must not exist>, <count that must be 0 with its command>

Frontend
-

Tests
-

CI and tooling
- `debt-dashboard.sh --final` exits 0

Runtime
- the runtime check of kit `05-program.md` D4 on <environment>: <the pages requested twice, the write>

Docs
-

## 5. Steps

Plan at step level (kit `05-program.md` D2). The step names and numbers are the kit's. "Exit artifact" is the one the kit's Exit line names, pasted or linked here.

| Step | What this project does in it | Counters it drives | Exit artifact | Runtime-check row | Owner's go (steps 1, 3, 10) |
| --- | --- | --- | --- | --- | --- |
| 1 Discover and define | | | | none | |
| 2 Make the checks tell the truth | | | | | |
| 3 Freeze the patterns | | | | | |
| 4 Test foundation | | | | | |
| 5 Layout | | | | | |
| 6 Types | | | | | |
| 7 Module hygiene | | | | | |
| 8 Patterns | | | | | |
| 9 Fill and trim | | | | | |
| 10 Lock and audit | | | | | |

A runtime-check row holds: the commit; each URL with its status, both requests; the error-tracker query and its count; failed jobs before and after; the result of the deploy pipeline. On a stack booted for the check the last and the third read "not applicable", with the count of error lines in the application log instead (kit `05-program.md` D4).

### Releases of a move with stored names (step 5)

One block per domain group (kit `05-program.md` D9).

| Release | PR | Deployed | Who rolls back, and how | Watch result |
| --- | --- | --- | --- | --- |
| 1 aliases and register | | | | |
| 2 the move | | | | |
| 3 data migration | | | | |
| alias removal | | | | the payload query and its result |

### PRs of the current step only

Rows for later steps are not written in advance: they go stale.

| PR | Area (backend / frontend / both) | Themes (one rule each) | Backlog ids | Proof and review aid | List deleted | Status |
| --- | --- | --- | --- | --- | --- | --- |

For a step split into more than one PR, say which reason applies (own deploy, human step first, hand-written limit).

### Audit log

| When (start, step N, end) | Auditor's findings | Became counters | Of a kind in the signed definition? |
| --- | --- | --- | --- |

## 6. Process facts this plan rests on

- What merges a PR; what resets approvals; who runs package installs; what may run locally; who announces; freezes; what must never run in parallel (kit `05-program.md` D5).

## 7. Decisions

Append-only. A changed decision is a new row that names the row it replaces.

| Id | Decision | Why | Decided by |
| --- | --- | --- | --- |
| D-01 | | | owner / owner (delegated) / lead, on request |

Open questions for the owner (real questions only, one subject each, with a proposal):

| Id | Question | Options | Proposal | Blocks |
| --- | --- | --- | --- | --- |

## Appendix: measure commands

In step 1 the three script commands run from the scratch copy (kit `02-step-1-discover.md` 1.6): `bash <scratch>/...` in place of `bash scripts/...`.

```
M-ratchet  bash scripts/ci-ratchets.sh --head
M-board    bash scripts/debt-dashboard.sh --details
M-full     bash scripts/checks.sh --scheduled
M-01       <command>
```
