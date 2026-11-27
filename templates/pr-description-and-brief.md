# Templates: PR description, builder brief, move map

---

## PR description

This is the one place that says what each section holds.

```markdown
<One line: what is true after this PR.>

## Benefit
- <what the team or product gets: faster to find, fewer bugs, less review effort, less risk>
- <2 to 4 bullets; not what the diff does>

## What this does
- <the change, grouped by theme>

## Verify
Rule: <the one rule a reviewer checks, in one sentence>.

- [ ] <at most 8 things to check by hand>

Proof of the mechanical part:
- `<command that re-runs the codemod>` gives an empty diff
- `<git grep for the old form>` returns 0
- `git diff -M --stat origin/<base>...HEAD | tail -1` (rename share)

## Hand-written parts
- `<file>`: <what and why; every file whose diff holds more than the codemod would produce>

## Behavior that changes on purpose
- <where production does something else after this PR: what it did, what it does now, why that is right. A bug a test on the real path exposed; a corrected lookup key, with what is stored under the correct key in real data and who checked it read-only. Write "None" when there is none>

## Hazards
- <stored names and which of the three releases this is; who rolls back and how; deploy order; environment keys that become required; classes left open and why; what the owner does by hand; what was not verified, and for every "confirmed" the method behind it>

## Counters
| Counter | Before | After |
| --- | ---: | ---: |

<the generated-with line the session gives>
```

A heading that does not apply to the change keeps its place and says "None". Docs-only PRs keep a "Why" section as the benefit. When the owner is the only human reviewer, Benefit, Behavior that changes on purpose and Hazards are the three sections the owner reads (`07-process.md` section 3).

---

## Builder brief

```markdown
# Brief: <theme>

Kit: `<absolute path of the kit>`. Hand-off folder: `<absolute path>`; it holds `zero-debt-plan.md`, `backlog.md`, `project-profile.md`, the copy of `08-builder-rules.md` with the project's ticket filled in, and this brief in a folder of its own.
You work from that copy of the builder rules and this brief; every kit section this theme needs is pasted at the end. Also read the plan's definition of clean and decisions (made, never reopen), and the root and scoped `AGENTS.md` of every area you touch.

## Goal
<What is true when you are done, and the rule that defines it.>

## Scope
- Files: <explicit list. A selector only when it gives the same files for every builder at every moment; never "the files in the diff">
- Scratch folder: <a folder of your own, outside the repository; no other builder writes there>
- Commit trailer (only where this brief gives you the branch): <the exact co-author line, copied from the session of the lead>
- Infrastructure names: <your container names, network aliases, volumes, ports and database name, each carrying the theme; no other builder uses one, and you touch no name you did not create>
- Backlog items: <ids>
- Out of scope: <what you must not touch, and who does>
- Shared files you may edit minimally: <list>, or none

## Worktree and git
- Worktree `<path>`, on branch `<name>`, made by the lead from `<ref>`. It is yours alone. / It is shared with <builders>: "In a shared worktree" of the rules applies, and your analyser config is `<file>`. <the lead keeps one of the two>
- You do not commit: report each finished part with a commit plan. / You own this separate PR's branch: commit on it yourself. <the lead keeps one of the two sentences>
- Another builder works in parallel on <theme>; stay inside your files.

## Method
- <codemod first, map file, order of steps>
- The list you empty: <file or baseline section>; it must be gone, or smaller by exactly what your theme covers. It is yours to edit, whatever the Scope says about its folder.
- The guard that holds this theme in this repository: <test file>. The ratchet rows this theme moves: <names>.
- A guard to write, only if named here: <mechanism, file>, with the spellings of the construct it must fail on: <list>. The lead adds its row.
- Fix patterns: <the one shared list, the same text in every brief of this round>.
- Test spelling for every test you add or move: <the one spelling the profile records, copied word for word; also the declaration form: `it(...)` or a `test` method, the casing, no `#[Test]` or `@test`>. The same text in every brief of this round, whatever spelling the old tests use.
- The one-pattern row to add or change: <row>.

## Proof you must be able to show
- <empty-diff, zero-grep, title-equality or byte-identity check>

## Do not
- Run <what the verification policy forbids>. Run a git command that changes state (unless this brief gives you the branch). Push, open a PR, merge, approve or comment. Run package installs. Add any exception entry, guard or ratchet row. Edit a script in `scripts/` or a pipeline file, unless this brief names it (then the commit plan carries the script tag, `[ratchet: script] <reason>`). Feed a formatter or fixer from a diff.

## Report
Worktree and branch, the commit plan, counts (files, ratchet rows before and after), what you left out and why, behavior that changes on purpose, open product questions, package commands for the lead, risks, and what you did not verify. For every "none left" or "confirmed": the command behind it and what that command cannot see.

## Pasted from the kit
<The lead pastes these in full, by the kind of theme, and deletes this list:
- every theme: the section headings of the PR description (this file), and the parts of `10-hazards.md` that match the theme ("Checks and tooling" always; "Tests", "Frontend", "The refactoring tool", "Big moves" where they apply);
- a pattern theme: its rows of the catalog, every row one of their cells refers to ("the same guard"), and the "Reading the Guard column" paragraph;
- a guard: "Rules for writing a guard" of `04-guards.md` C1;
- a theme that puts tests on the real path: `10-hazards.md`, "A test with exception handling off", and the catalog row "Middleware around the next handler";
- analyser errors: `05-program.md` D7, points 4 and 6, and the catalog's list of fix patterns;
- closing classes: `05-program.md` D8;
- a move or rename: `05-program.md` D9, `10-hazards.md` "Stored names", and "Move map" of this file.>
```

---

## Move map

One file per move theme, committed with the PR, named `moves.tsv`. Plain text, one line per class, two columns separated by one tab: the old name, then the new one. No header line, no blank lines, sorted by the old name. Every old name appears once; no new name appears twice. The name is what the language stores and imports: in PHP the fully qualified class name; in TypeScript the file path from the repository root, `#`, and the export name (`src/orders/create-order.ts#createOrder`).

```
<old name>	<new name>
```

A project that moves classes keeps beside the map (a) a group column or one map per group, so the tool applies a group at a time; (b) a second list of the classes that are not in the map, each with its reason (a stored name, converted in step 8, kept by a guard), so the decision table of the PR description is written once; (c) for a renamed class the kind of rename (suffix, collision). The format is direction-neutral: the same tool run with the columns swapped (`--reverse`) takes a group back out, and the path-keyed lists are restored for exactly those files.

Everything else is derived from it and never typed by hand (`05-program.md` D9): the move tool's rename rules, the alias list in both directions, the data migration and its `down()` (the same map read in reverse), the rewrite of path-keyed lists, the new register lines, and the reviewer's proof that a `git grep` for every old name returns 0. The script that derives them is committed in the PR.
