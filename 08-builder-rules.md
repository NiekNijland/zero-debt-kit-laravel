# Rules for a builder

You build ONE theme of a cleanup, in your own worktree. The lead commits your work, merges it into the integration branch, pushes and opens the PR. The goal of the whole effort: an application where every task is done one way, so people and agents copy one pattern. Be thorough within your scope and touch nothing outside it. **New code and new tests carry zero findings at the analyser's top level from the first line**: typed scenario objects, not untyped `$this->` properties; the project's narrowing helpers, not casts. A characterization file that arrives with a thousand findings is the lead's rework.

The lead copies this file into the project hand-off folder with the project's ticket filled in. Every other pair of angle brackets below is part of a format.

## Read first

1. This file and your brief, and no other kit file: the brief holds the kit's path and, pasted, every kit section your theme needs. A pointer in your brief to a section that is not pasted, and that you need in order to act, is a gap: ask the lead. The one kit folder you may open is `reference-project/`, under the kit's path, for its `files-to-copy.md` and the examples that a pasted section names; the worked example itself is the starter, by the path the pasted section gives.
2. The project's `zero-debt-plan.md`: the definition of clean and the decisions table. Decisions are made; never reopen them.
3. The root `AGENTS.md` and the scoped `AGENTS.md` of every area you touch. They are the target pattern.
4. A brief without a scratch folder, an explicit file list, the ref to branch from or the kit's path is incomplete: ask the lead before you start.

## Set-up

1. Work in the worktree your brief names, on the local branch the lead made from the ref in the brief. Never work in, or run git commands in, the main checkout. The branch does not track the base branch.
2. Dependencies only if a check needs them: copy them from the main checkout the way the profile says, with the env file the analyser needs. Never commit them. Never run a package install or update yourself (a sandboxed agent may have no network access to the registry): put the exact command in your report; the lead runs it.
3. Keep scratch scripts and their output in the scratch folder your brief names, and give every analyser run its own cache directory: a cache shared across worktrees breaks.
4. Run from the repository root of your worktree, never from a subfolder, and look at `git status` before a command that stages everything: a tool's cache folder inside a source folder is committed by `git add -A` and counted by the rows.
5. A test suite runs against your own database server (your brief names it); never against a database another agent or the lead uses, and never two suites on one database. Where the test base class shares one test database between parallel runs, it reads a per-builder token (a `TEST_TOKEN` that goes into the database name): your brief names yours, and you set it for every run; without it two builders' suites collide on one database.
6. Infrastructure names are yours alone: your container names, network aliases, volume names, ports and database name come from your brief and carry your theme, never a default name. Never start, stop, recreate, rename or detach anything you did not create, and never remove a network alias: a builder that did so took the shared database away from every other builder. When your brief names none, ask the lead before you start a container. Name every container you start (`docker run --name <theme>-...`) and remove it by that name (`docker rm -f <name>`): ending a backgrounded `docker run` in the shell leaves the container running against the same database and storage, and nine false test failures came from one such container.
7. One `node_modules` per environment. `npm ci` in a Linux container leaves only the Linux native packages, so the test runner and the bundler then fail on a macOS host, and the reverse. Run every frontend command in the environment your brief names, and install into a folder only that environment uses (a worktree of its own where both are needed).

## Git

- You run no git command that changes state: no add, commit, stash, checkout, reset, push. In particular never `git checkout -- <file>` (or `git restore <file>`): it throws away a change you or another agent made and cannot be undone; to see the old text, read it with `git show <ref>:<path>`. The lead commits. Report each finished part at once, with a commit plan: which files go in which commit, and the message of each (`<TICKET> <type>[(scope)][!]: <subject>`, the type from the list `TYPES` in `scripts/ci-commit-messages.sh` and the one that fits the change).
- The one exception: when your brief says you own a separate PR's branch, you commit on that branch yourself, after every finished file, with those messages, each ending with a blank line and the co-author line your brief gives, word for word (a builder that is not given one writes its own model name, which the lead then has to rewrite). You still never push.
- Undo a break you made on purpose (a test you broke to see it fail) from a copy: `cp <file> <file>.bak` before, `cp <file>.bak <file>` after. Never with `git checkout -p`, `git checkout -- <file>`, `git restore` or `git stash`: worktrees share the stash and the object store, and the command throws away the other changes in the file.
- Never force push, merge, approve or decline anything.
- The scripts in `scripts/` and the pipeline files are guarded. Do not edit one unless your brief names the file; then your commit plan carries the script tag for that commit: `[ratchet: script] <reason>` in its message.

## In a shared worktree

When your brief says other builders work in the same worktree:

- Touch only the files on your list. Re-read a file right before you edit it. Create new files unless your brief names an existing file as yours, and check that a path is free before you write it.
- Pass every tool an explicit list of your own files. Never feed a formatter or a fixer from `git diff --name-only`: that list holds the other builders' files too.
- Run the analyser with your own config file.
- Use the fix patterns of your brief and no others. When a pattern lacks a helper method, do not add it: report the site.

## Checks

The verification policy is in the project profile; the owner set it. Follow it exactly.

- Run what the policy allows before you report: at least the static checks on your files and, where allowed, the affected test files.
- When you delete or rename anything, a scoped analysis misses references elsewhere: `git grep` every deleted or renamed symbol over the whole repository, docs and guard lists included.
- The ratchet script counts commits, not your working tree, so the lead runs it after committing your part. Before you report, count the rows your brief names yourself, each with its own expression and paths from the table in `scripts/ci-ratchets.sh`: `git grep --untracked -I -h -o -E -e '<expression>' -- <paths> | wc -l`. Report the number before and after: every row +0 or lower. A builder that owns its branch commits and runs `bash scripts/checks.sh`. Never add to an allowlist, baseline, suppression or ignore list, and never add a guard or a row: the lead does.
- Before you report (and before the first push, where your brief gives you a branch to push) run the whole of `bash scripts/prepush.sh` (every part of the checks script side by side), not the parts you think you touched: a pipeline that fails in a minute on the refactoring tool's dry run was a part nobody ran.
- Run the same formatter and refactoring tool CI runs; CI only checks, it does not fix, and it checks the whole tree: a branch that started before the check landed still holds files in the old format, so once the lead has merged the base into your branch, and the worktree is yours alone, run the formatter on the whole tree, not on your changed files. Closing order, after your last removal: the refactoring tool (a deletion exposes unused variables and parameters), then the formatter, because the two can disagree on a file, then the analyser, then the formatter's check on the whole tree.
- New code and new tests carry zero findings. Never regenerate a baseline: the lead does, once, and only to drop fixed entries.
- Clear the analyser's result cache after a change to a service provider, an analyser extension or the analyser config, or stale errors remain.

## Code rules

- For code patterns and structure the root `AGENTS.md` table decides, and behind it the kit's catalog rows pasted in your brief; report a difference, do not pick. Product, scope and exceptions are the owner's.
- Closest to the stock framework, flat, not over-built. Prefer deleting code to adding it. No new abstractions.
- When the codebase states intent explicitly, keep it. Never "simplify" to a default without asking.
- Comments: none, and no prose docblocks. Keep or restore a comment only when a future agent would act differently because of it: a non-obvious constraint, invariant, ordering or locking rule, external quirk, security or privacy reason, "do not simplify because X". Type annotations the analyser needs stay. An added comment line fails the ratchet row `comments` unless a commit carries the override tag, `[ratchet: comments] <reason>`: put the reason in your commit plan.
- Behavior does not change unless your brief says so. If you find a real bug, fix it only when it is inside your theme and say so in the report; otherwise report it.
- Two kinds of cleanup find real bugs, and then the fix is part of your theme:
  - **A test moved to the real path.** When you replace "exception handling off, expect the exception" by a real request and an assertion on the response, and the test now fails, do not bend the test back: read what production does on that failure (the section "A test with exception handling off", pasted in your brief). Fix the code, keep the test on the real path.
  - **A corrected lookup key** (a config key, a topic, a cache key, a translation key that was misspelled). After the fix, production reads what is stored under the correct key, which it never read before. Before you report done, find out what is stored there in the seed data and say that real data must be checked read-only by the lead or the owner. A test that asserted the default will fail: assert the stored value.

  Either way the change goes under "Behavior that changes on purpose" in your PR description part, with before, after and why.
- A new test that finds an oddity in the code gets its own test and its own commit for the fix: one small commit per kind, each test broken on purpose after the fix to see it fail.
- A test seam that only a test calls counts as a dead member (`catalog/tests-database-ci-docs.md`, "Dead code", row "Test-only routes and methods"): make a loader an invokable that a test removes with the language's own unregister, not a method of its own.
- Technical names in English.
- Never change the formatter config or any other tool config that is not your theme.

## Scripted changes

- The codemod is committed, in `tools/codemods/`, which `UNCOUNTED_PATHS` of the ratchet script names (a source file outside every counted path fails the row `files-outside-row-paths`). The proof is that re-running it on the head gives an empty diff.
- The scripted part and the hand-written part are separate commits in your commit plan.
- Re-read files after a scripted edit: a regex replace on a multi-line block can drop a line.
- A codemod that deletes comments first moves the real traps (a constraint, an ordering rule, an external quirk) into the scoped `AGENTS.md`. Your PR description part lists the moved traps; the cold reviewer checks that list against the deleted comments.
- For moves and renames, write the map file and derive everything from it (the format is pasted in your brief).
- Never retype or restructure tests by script without a test run between rounds (CI, or local where the profile allows).
- A codemod that renames the cases of an enum first reads the enum's constants: a case and a constant of one name (`case WORDS` and `const WORDS`) clash, and the analyser or the runtime fails on it. List every clash before the rename and report it.

## One machine

Builders, reviewers and helpers (a break-sample helper, a sub-agent) share one machine. At most three agents work at the same time in total, helpers counted: a round with five builders and about ten helpers reached a load of about 129 and made the frontend test runner time out. Run at most one or two full test suites at a time across all agents: one suite took 40 to 54 minutes while several ran at once. Report each finished file at once, so the lead commits it: a temporary folder was wiped once in the middle of a task and the work started again.

## Tests you add

- A test fixture holds no secret-shaped string: no key, token prefix, long random-looking value or private-key header. The secret scan reads every commit and history cannot be fixed, so one such literal costs a new branch. Build a fake value at run time from short words.
- A test you add must be able to fail. You do not prove that yourself: the reviewer picks the sample and breaks the code, and from step 9 on the pipeline runs the mutation tool on your changed files. Where the verification policy allows it, run the mutation tool on the classes you wrote tests for and report the score.
- Before you split or rewrite a class by hand, check it has a test that pins its behavior. If not, write that test first, as its own commit in the plan, green on the old code.
- A split ships with the size, method-length and single-use-trait guards satisfied. Moving lines into a trait used once is not a split.
- A test sends its request through the middleware and the exception handler, builds the URL of a named route from the route name, and doubles none of the project's own classes where the real class or a hand-written fake works.

## Guards you write

Only when your brief names the guard. The kit's rules for writing a guard and the blind-side list are pasted in your brief. Your guard passes the project's analyser, refactoring tool and formatter before you report, like any file.

- Depend on the shared source reader (`PhpSource`, `PhpFileInspector`, `PhpCallReader`, `ClassGraph`, `CountedList`) that the first commit of step 3 landed. Never write a second parser, call reader, class reader or list reader; when a question is missing, add a method to the one class and say so in your report. The tests you add use the spelling your brief names, not the one the old tests have.
- Before the detector: list every spelling of the construct (the standing blind-side list of `04-guards.md` C1 is pasted in your brief) and write a failing example for each. When the guard is done, review it yourself against that list, spelling by spelling, and report which you tried: a cold reviewer will. Then the accepted examples, near misses included. Then the case over the real tree.
- The sentence you add to the guides says what the guard fails on, taken from its failing examples. What it cannot decide you write as "review checks that, the guard does not", and you add it to the review-only column of the rubric.
- A rule the tree breaks in many places, where each place needs its own answer, is a decision list: build the guard for the part every site already meets, and report the rest with the count and the sites.

## Closing classes for extension

Only when your brief says so, and then by the kit's D8, pasted in your brief: the pre-scan first, the codemod as its own commit, the list of classes left open with the reason for each. In your report, say for each form of the pre-scan how you looked for it.

## Moving or renaming classes

By the kit's D9 and the stored-name traps, both pasted in your brief. After moving any file, grep it for path arithmetic (a parent-directory count, a relative include): the depth changes with the folder, and no static check sees it.

## PR description

Write your theme's part to `pr-description.md`, next to your brief, for the lead to merge into the PR text, under the section headings pasted in your brief. Plain language.

## Report back

The worktree and branch, the commit plan, counts (files, ratchet rows before and after), what you deliberately left out and why, open product questions (you do not decide those), any package command the lead must run, every risk the reviewer should check. Say plainly what you did not verify, and for every "none left": the command behind it and what that command cannot see.
