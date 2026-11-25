# Step 10: lock to zero, audit, keep it there


## E1. Lock

When a counter reaches zero:

1. **Delete the list.** The guard no longer reads a list at all; the list file, its "no new" and "no stale" tests and the ratchet row the project added for it are removed in the same PR (the commit that removes the row from the table carries `[ratchet: <name>] list emptied and deleted`, `04-guards.md` C2). There is one form: no empty list file stays behind. A guard fails when a list comes back, without naming any file: the guard folder holds detectors and registers only, so the guard fails every other data file in it, and it fails an analyser baseline include, a bulk-suppressions file and a keyed-baseline file wherever one appears (`catalog/tests-database-ci-docs.md`, "CI and dependencies", row "Lists stay deleted").
2. The rows of the start set stay, at 0 (`04-guards.md` C2).
3. Remove the bulk suppressions or the analyser baseline entries for that rule; when a baseline file is empty, delete it and its include in the same PR. Its row `analyser-baseline` is of the start set: it stays, at 0.
4. Delete the codemod script, unless a later step re-runs it.
5. Update the one-pattern row: it now says "no list".

When every counter is at zero: check that no alias of a moved class is left (alias removal is the open loop of step 5, `05-program.md` D9: step 10 does not close before it), re-read every `AGENTS.md` for sentences that still describe a transition, and switch the dashboard step to `--final` (`04-guards.md` C3): `DASHBOARD_MODE="--final"` in `scripts/checks.sh`, in a commit with the script tag (`04-guards.md` C2). The scheduled job follows from the same line. Before that commit, lower every cap to its row: `--final` fails a cap that is above its row.

## E2. The audit runs three times, not once

An audit that runs only after "zero" is declared finds the other half of the program. So:

- **At the start** (`02-step-1-discover.md` 1.8): the answer becomes counters before the definition of clean is signed.
- **After every step** (`05-program.md` D4, point 3): one question about that step's kind of debt.
- **At the end**: the full audit.

The auditor is an agent that built none of it. It gets the tree, not the plan's conclusions. Its brief and the one list of what it looks for are in `09-reviewer-brief.md` ("Auditor").

Every finding becomes a backlog item with a count and a guard (`04-guards.md` C7).

**The end rule.** A language model always finds something, so "repeat until nothing is found" never ends. The audit is done when two fresh auditors in a row, each without the other's report, add no counter of a kind in the signed definition of clean. A finding of another kind goes to the after-zero backlog with its count, and to the owner as one question: add this kind to the definition, or not. Only then is the program done.

## E3. Keeping zero at zero

The definition of done in `01-principles.md` and the CI shape in `04-guards.md` C4 are what holds it. Three things to check at the end, because each is easy to believe and wrong:

1. The dashboard runs in the pipeline as a blocking step with `--final`, and the scheduled job runs it in full once a day. The profile names who owns a red scheduled run from now on: the repository's maintainer. A major version behind, an advisory, an abandoned package or a living doc past the age limit turns it red with no code change, and is fixed like any finding (one with no fixed release upstream: the decision row of `04-guards.md` C3); minor and patch updates never do.
2. The formatter checks the whole tree in CI and blocks, and nothing commits (except an autofix commit the owner kept in step 1, named in the profile).
3. Every guard test file runs in a CI step (the contract test over the CI files).

After that: a PR that introduces a recurring task adds its row and its guard; when a reviewer flags the same thing twice, it gets a guard.

**The drift scan.** When product work has merged on top of "zero", scan the pull requests merged since. Per PR, ask: which rule of the one-pattern table or the rubric did it leave, and why did CI let it through? Then count the whole tree for each such rule: one PR's slip usually sits on a much larger standing count. A finding is a guard candidate (`04-guards.md` C7), not a reminder for reviewers.

The scan is repeated, not done once: each round asks the same question of the rules that are still only written down. The lesson behind it is in `record-source-project.md`, "Rules and their guards".

## E4. Closing the program

- Final dashboard output (`--final`) in the plan, with the commit hash, the two final audit reports, and the runtime-check row of that commit (`05-program.md` D4, point 5).
- Backlog: every item done, closed with a one-line reason, or moved to a named follow-up with its "start when" signal.
- **Archive the plan in the repository**: `zero-debt-plan.md` with its decisions table goes into the archive folder of the repository's docs (`docs/archive/`), in a docs PR. The row `docs-older-than-limit` does not count that folder or the decision records: they are history. The other hand-off files stay where they are.
- `CONTINUE.md` says the program is closed and lists what the owner still does by hand. Deploy-bound steps belong to the owner: orphan checks before a foreign-key migration, new secrets in the secret manager, re-issued tokens, restarting what ran in a recreated container, production checks, visual checks at phone and desktop width.
- Clean up worktrees (check `git status` in each first), local branches and scratch files.
- **Feed the kit.** Write what this run learned into `10-hazards.md`, as a trap that ends in its instruction, and, where the order or a rule proved wrong, change the kit itself. A way chosen for a task the catalog did not cover becomes a catalog row (`03-conventions.md` B1), with its row in `catalog/other-stacks.md`. The tooling table a run built for a stack the kit had none for goes into `catalog/other-stacks.md`, "Tooling in another stack", and its formatter config into `templates/tool-configs.md`. Strip everything that names the project. The kit should be better after every project.
