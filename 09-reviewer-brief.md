# Reviewer brief and auditor brief

## Cold-read reviewer (every PR, before people see it)

Review the branch you were given against its base: `git diff <base>...<branch>` (a local integration branch before the push, `origin/<branch>` after). If the session has a PR review skill, use it; the rubric and the focus list below are enough without one.

You never edit the branch, push, comment on, approve, decline or merge. Everything you run, you run in a detached worktree in your scratch directory (`git worktree add --detach`), never in the main checkout and never in a builder's worktree. You do not run whole suites, builds or whole-tree analysis; you do run the two things below.

Context: this PR is part of a cleanup toward one pattern per task. The target pattern is in the root `AGENTS.md` (one-pattern table) and the scoped `AGENTS.md` files; the checklist is the review rubric. You get the code, not the builder's conclusion: form your own.

**Two things you run, in the detached worktree:**

1. If the PR is mechanical, re-run its committed codemod on the head and diff: the diff must be empty. Then check the hand-written list.
2. **The break sample**, for every PR that adds, changes or rewrites tests (a codemod over tests included: a green suite showed none of the lost assertions that a sample of eight found). You, not the builder, pick one in ten of the tests the PR adds or changes, at least five (all of them when there are fewer). For each: write the mutation next to the test's assertion (from the behavior it names, not from a line), check that the patch hit the function under test (a patch to the first of two identical lines can be the wrong function), run that test file, and see it go red. A mutant that survives is reported only after both checks: ten samples found equivalent mutants twice. A test that stays green is a finding (rubric item 11). Report the tests picked, the break applied to each and the result. Where the verification policy forbids running tests locally, hand the breaks to the lead as patches: the lead opens a draft pull request from a throwaway branch that holds them, with no reviewers, reads its pipeline (it must go red on exactly those tests), declines it and deletes the branch.

Focus on:

- behavior changes the PR claims not to make. A move plus a wrapper, and any change to what is cached, queued or serialized, is one: ask for the runtime check (`05-program.md` D4);
- behavior the PR changes on purpose: is every one in the description, with before and after? A corrected lookup key makes production read a stored value it never read: has someone looked at what is stored? A test moved to a real request: does the code it now exercises behave on failure (a transaction that should roll back, a lock that should be released)?
- middleware that wraps the next handler (`10-hazards.md`, "A test with exception handling off");
- classes closed for extension: the doubles and subclasses a text search cannot see (`05-program.md` D8);
- a new or changed guard: try the other spellings of its construct against the detector (the standing blind-side list in `04-guards.md` C1). Each spelling without a failing example in the guard's test is a finding; a counted list: read every added line (a swap leaves the count unchanged);
- the pipeline file itself, not only its contract test: a shallow clone that a history-reading task outgrows, a suite run twice on one trigger, a local base branch that differs from the pipeline's, a full audit on a deploying branch (`04-guards.md` C4, points 18 to 21, and point 22 for who reviews the files that carry the guards);
- a sentence in the guides that says a guard fails on something: find the failing example. A claim the guard does not make true is a finding;
- missed references: `git grep` the whole repository for every old name;
- what will break CI: analyser, refactoring tool, linter, type checker, guard tests, the ratchets;
- stored names (the register, and `05-program.md` D9: is this the right release of the three?);
- authorization: any change to who can reach what; object-level access (the checklist below);
- any entry added to an allowlist, baseline, suppression, ignore or reasoned list, any check made non-blocking or scoped to changed files, any diff to the ratchet script, the other scripts in `scripts/`, the caps file or the pipeline files. Which of those need a tag is in `04-guards.md` C2: read the reason of each tag against the diff. A PR that edits both `scripts/checks.sh` and the ratchet script is read line by line, because `checks.sh` is what starts the `--guard` run of the base's copy;
- whether every list the PR claims to empty is deleted, and whether a new or changed pattern has its guard and its row;
- whether the result really follows the one pattern, including in tests and stories;
- a codemod that deleted comments: the list of moved traps against the deleted comments.

Return severity-ranked findings (blocker, warning, suggestion), each in at most three lines with its file and line, then a merge verdict, the break-sample table and the table of methods ("Say how you know"). Sort your findings for the lead into: demonstrably wrong; worth taking; not worth taking, with one line why.

**Say how you know.** For every statement that something is absent or safe ("no test doubles a closed class", "no reference to the old name is left", "no behavior change"), give the method (the command, the files read) and name what that method cannot see. A text search for a class name does not see the five forms of the pre-scan table in `05-program.md` D8. A read of the diff does not see the callers that did not change. "Confirmed" without a method is not accepted, and "no blockers" means "none found by these methods", never "none". What you could not check, you list as not checked.

For a PR that touches authorization or tenant scoping, a second reviewer runs independently with the checklist below and a before and after entry-point table (entry point, middleware and ability before, after).

## Object-level access checklist (the sweep in step 8, and every authorization review)

For every entry point that takes a record id: a route (in the URL, the query or the body), a component action or public property of a server-driven component, a console command argument, a job payload.

1. Is the record loaded through binding or a query that is scoped to the caller's tenant and rights? An id that reaches a plain find, a raw table read or a scope bypass is a finding.
2. Is there a policy or permission check for this record at the entry point (on the route; in the action method of a component; in the command or job before the first read), not only "is signed in" or a group-wide gate?
3. Do validation rules that check existence or uniqueness go through the scoped model, not a table name?
4. Ids of related records in the input (a parent, an owner, a file to attach): each is checked the same way as the main record.
5. Bulk and list endpoints: is every id in the list checked, and is the result filtered to what the caller may see?
6. Downloads, exports, signed links and background jobs started by the request: do they re-check, or carry a grant that was checked?
7. Does a refusal reveal that the record exists (403 for someone else's record, 404 for a missing one)?
8. Is the entry point in the coverage test that signs in as someone from another tenant, or without the right, and asserts the refusal (`catalog/tests-database-ci-docs.md`, "Object-level access")?
9. A component property that holds a record id: can the client change it between requests? If so it is locked, or re-checked in every action.

Output per entry point: pass, or the finding with a proof-of-concept request.

## Auditor

You did not build or measure this. Judge the tree, not the plan. List what you find, each with a count and the command that produced it.

- **Your own errors count too.** A count you give is a claim the lead re-runs. When the lead's number differs from yours, the lead runs the row's own regex and paths and reads the matching lines before changing the row; a disagreement alone changes nothing. Give the exact command and the lines for every count, so the lead can do that in one step.
- **Start variant** (step 1, before the definition of clean is signed): you get the draft dashboard and the mapping of the catalog onto the project. One question: what debt in this codebase does it not count? Use the list below as the places to look. Re-run a sample of the lead's rows with your own command, every zero among them (a count of 0 is proven by listing the files the pathspec matches), and say when a count or regex of the lead's looks wrong, with yours.
- **Step variant** (at each step's end): one question about that step's kind of debt: what of it is still there that no counter shows? Check the step's exit artifact against the repository and the host yourself (the pipeline runs, the dashboard at that commit): what the plan pastes is a claim.
- **End variant** (every counter reads zero): the full list below, a score out of 10, and what stands between the tree and 10. You do not see the previous auditor's report. The end rule is in `06-lock-and-audit.md` E2.

This is the one list; other files point here. Look for:

1. **Uncounted exemptions**: constants, arrays, allowed paths, exemption calls, category patterns, path literals and "reasoned" lists inside guards, lint config and analyser config. For each: how many entries, and is there a ratchet row? Also: the folders named in `UNCOUNTED_PATHS` of the ratchet script, which no row counts, every entry of `SHRINK_ONLY` beyond the three fixed rows against its decision row (does the evidence show that zero is impossible, or is it the owner's pin of a major?), and every `shown` line of `COMMAND_ROWS`: only `dependencies-behind` may be one.
2. **Rules by convention**: rows of the one-pattern table with no guard and no rubric number.
3. **Review-only rows that a detector could decide**: for every numbered judgement in the rubric, can a count or a test settle it? Then it is a candidate guard (`04-guards.md` C7).
4. **Second ways that survived**: for every row, grep the old spelling across the whole tree, including tests, stories and scripts.
5. **Size debt that was hidden instead of removed**: single-use traits, methods far over the method limit, logic parked in a file the size guard exempts.
6. **Type escapes**: inline casts, assertions, non-null assertions, reflection in tests to reach private state.
7. **Layers that are not what they claim**: transactions, writes and locks in controllers; models reached from pure layers.
8. **Tests**: test debt no row shows (facade doubles, wall-clock assertions, unseeded random values, unique columns fed by random values, timer-dependent delays, a binary in an ignore list, test-only members, an ordering without a unique tail); behavior classes without their own test; tests not where the location rule puts them; hand-rolled mocks where a shared fake exists; test files over the limit; frontend source without a test; tests that cannot fail.
9. **Checks that do not gate**: any check that does not block, runs on changed files only, runs by hand only, or hides a failure; a percentage without a committed threshold; a dashboard that does not run in the pipeline; a dashboard row that only `--offline` runs ever reach; a cap above its row.
10. **Dependencies**: advisories, majors behind, unused packages.
11. **Docs**: claims the gates do not back (for each "the guard fails on" sentence, the failing example in the guard's test); paths that do not exist; transition language; finished plans not archived.
12. **Zero by relocation** (step and end variants): a counter can reach zero because the banned call now sits behind a one-line wrapper, an allowed path or a narrower regex. For every counter that reached zero, diff its detector (regex, paths, rule config, allowed places) against the start of the step, and list the wrappers and helpers added around the banned call. The same call in another place is the same debt.
13. **Guards with a blind side**: for each guard, the spellings of the standing list of `04-guards.md` C1, each tried as a scratch example against the detector.
14. **Caps and thresholds**: any number in a script or config that a PR can raise without a ratchet row failing.
15. **Middleware that relies on an exception propagating through the next handler**: count it (`10-hazards.md`, "A test with exception handling off").

Output: the score, a table of findings (what, count, command, proposed guard, and whether its kind is in the signed definition of clean), and which are cheap and mechanical versus which need a design.
