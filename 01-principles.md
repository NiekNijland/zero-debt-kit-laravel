# Principles

## The goal

A predictable application: someone who knows the framework opens any file and finds what they expect, in the place they expect, done the way every other file does it. Then: zero technical debt, measured, and no way back.

Two results, in this order:

1. **One pattern.** Each recurring task (authorize a route, validate input, read config, report an error, write a test helper, format a date) has exactly one way. That way is closest to the stock framework, flat, and not over-built. It is the same way in every project that uses this kit (`catalog/`), so people and agents move between projects without relearning.
2. **Zero debt.** Every suppression, baseline, allowlist, skip, oversized file, weak type, outdated package and untested behavior class is counted and brought to zero. At zero, the list is deleted and CI fails if it comes back.

## Who decides what

This is the one place that says it; other files point here.

- **The kit decides code patterns and structure**: the one way per task, the layout, the limits, the strictness, the guards. Code that does it another way is debt, not a variant. Only techniques differ between projects: the database engine, the CI host, the language. Another technique gets the same pattern in its own terms (`catalog/other-stacks.md`).
- **The repository decides its own business**: product rules, copy and wording, domain names, who reviews, what may run locally, the release process. A house rule about a code pattern or structure that differs from the catalog is a backlog row.
- **The organisation above the repository decides what it manages for many repositories**: a formatter or linter config it hosts (often fetched from a URL), shared pipeline images, an autofix commit or a merge-back bot in the pipeline, the deployer, the release flow. These are not code patterns and the kit does not replace them. Where one conflicts with a kit rule, step 1 asks the owner (`02-step-1-discover.md` 1.1), with the kit's reasons, before any pull request; a measured "nothing changes if we swap" is not a reason to skip the question. What stays the kit's concern is that the check runs on the whole tree and blocks.
- **The owner decides product and scope**: behavior that users or other systems notice, privacy, what is deleted, and exceptions to a kit rule. An exception is a decision row with its reason (`03-conventions.md` B4). Details are never asked (`03-conventions.md` B5): which guard to build, which tool and which threshold are the kit's or the lead's; which row may stay above zero is the owner's, as a decision row with an end date ("Shrink-only rows" below).

## Rules that never bend

1. **Guards only shrink.** An allowlist, baseline, suppression, skip, ignore or reasoned-exception list never gets a new entry to make a build green. Fix the code, or ask the owner. The one dated exception is a line of `scripts/audit-exceptions.json` for a fresh advisory ("Dependencies and CI" below).
2. **Every pattern has a guard and a row** in the one-pattern table of the root `AGENTS.md`. A rule that can be checked mechanically gets a guard, without asking the owner per rule: one guard too many costs less than one too few. Review only is for judgements, and each one is a numbered item of the review rubric (`templates/repo-docs.md`). Where a yes or no test cannot judge, a counter that only shrinks is the guard (`04-guards.md` C6). A document says "guarded" only for what the guard decides (`04-guards.md` C1).
3. **No list beats a short list.** Prefer a rule that holds without exceptions. When exceptions are unavoidable, exempt by category (generated files, vendored components), never by a list of files that can grow silently. Count every exception list on the dashboard, including "reasoned" ones inside a test.
4. **CI checks the whole tree**, not the changed files. Every check blocks: no `warn`, no `|| true`, no `continue-on-error`. A lint rule is `error`; a rule that is switched off is a counted exception (ratchet row `lint-config-off`) that goes to zero, except the one category under "Shrink-only rows". A formatter in CI checks and fails; it does not commit, unless the owner kept the organisation's autofix commit in step 1: that exception is named in the profile and the contract test (`04-guards.md` C4).
5. **Guard first, then burn down.** A guard goes in as early as possible with a counted list of today's violations that can only shrink (`04-guards.md` C5). Converging a pattern without its guard lets the old way grow back while you work.
6. **Behavior does not change** in a cleanup PR unless the PR says so in its first lines and the owner agreed. A refactor runs under the existing tests. A move plus a wrapper, and any change to what is cached, queued or serialized, is a behavior change: it gets the runtime check of `05-program.md` D4. Two cleanup steps find real bugs: putting a test on the real path (the real request, middleware and exception handler), and correcting a lookup key that was misspelled. The fix changes behavior on purpose; it belongs in that PR, under "Behavior that changes on purpose" in its description (`templates/pr-description-and-brief.md`).
7. **Delete rather than add.** No new abstraction, service, cache or lock without a proven problem. When the stock framework has a way, use it. When the codebase states an intent explicitly, keep it; do not "simplify" it to a default.
8. **Measure, then act, and freeze the yardstick.** Every claim in a plan is a count with the command that produced it, or it is labeled "not measured". Re-measure when a step ends. A counter's definition does not change silently: redefining one is a decision row that shows the number under the old and the new definition. The definition of clean is signed by the owner before the first code change and only grows.
9. **New code copies the example file, not the neighboring file.** Every row of the one-pattern table names a real file that passes every rule of the table. A guard holds that: an example file appears in no list, baseline or suppression (`catalog/tests-database-ci-docs.md`, "Docs").
10. **Decisions are written where the work is.** During the program that is the decisions table of the plan, in the hand-off folder, with who decided. A decision that binds code is also written in the repository, in the `AGENTS.md` files and the decision record of step 3. When the program closes, the plan is archived in the repository (`06-lock-and-audit.md` E4). A decision that is made is not reopened by a review.
11. **Reality over notes.** Statuses in hand-off files go stale. Fetch, list PRs and read CI before relying on them.
12. **Safety.** Never touch production, never print or store secrets, tokens, env contents, customer data or personal data. Read-only inspection only. Deploy-bound steps (data migrations, alias removal) are done by the owner or with their explicit yes.

## Definition of done

There is one definition of done and one standard: zero means zero. The definition below is the definition of clean the owner signs in step 1; it has no lighter form. Any exception is a counted row with an end date and an owner's approval ("Shrink-only rows" below); nothing else is an exception.

For one PR: merged; its guard is live; the counters it drives are lower or zero and the dashboard says so; the backlog and plan marks are updated.

For the program, all true on the main development branch:

**Structure**
- Every class sits where the layout table says; the layout list is gone.
- No file and no method over the kit's limits (`catalog/backend-laravel.md`, "Structure"; `catalog/frontend-react.md`, "Strictness"; for another stack `catalog/other-stacks.md`, "Structure").
- One module per domain; no duplicate or near-duplicate modules.
- Every concrete class is closed for extension, and a parent class is abstract; a concrete class that stays open is a decision row. The formatter closes classes and the dashboard row `open-classes` counts what it left open (`catalog/backend-laravel.md`, "Final by default"). In a language that cannot close a class, no class extends a project class except an abstract base (`catalog/other-stacks.md`, "Final by default").
- Every name that data depends on is in the register of stored names, and no alias of a moved class is left.

**Static analysis and types**
- The analyser runs at its top level, on source and tests, with no baseline, no ignore entries, no excluded paths, no inline ignores, and full type coverage. Every strictness switch the catalog names is on: the row `analyser-switches` holds them, and `--final` fails it below `ANALYSER_SWITCHES_TARGET`.
- The type checker of the frontend runs strict, with the extra strictness flags on, zero suppressions, zero type escapes and no keyed baseline.
- The analyser also runs the checks of its next major (the bleeding-edge include) and reads every folder that holds code of the project (`catalog/backend-laravel.md`, "Analyser").
- The refactoring tool has every set of the kit's config on and no skip entry: the rows `refactor-sets` and `refactor-skip`. Its dry run is clean on the whole tree. Dead-code detection runs on the whole tree and is clean.
- The formatter config holds every rule of the strictness list, each one on: the row `formatter-rules`, and each counting row of a behavior-changing rule at 0 (`templates/tool-configs.md`, "Rules that can change behavior"). Where the organisation hosts the config, the rules the hosted config lacks are a decision row each and the counting rows stay; the entry of `formatter-rules` in `MIN_ROW_TARGETS` is then lowered to the number of rules the hosted config has, in a commit with the script tag and the decision row, and the dashboard prints `ok (goal lowered ...)` on every run, so the lowered goal stays visible.

**Lint**
- Linter on all files: zero warnings, zero errors, zero disable comments, zero bulk suppressions, zero rules at `warn`, zero rules switched off (the one kind that stays is under "Shrink-only rows").
- Unused-code tool clean with no ignore list.

**Patterns**
- Every row of the one-pattern table has a guard, or is a numbered review item in the rubric.
- Every banned call has zero sites outside the one place that owns it.
- Every construct with two spellings has one, in source and in tests.
- The framework's strictness switches are on, as calls in the one application service provider, and the config test asserts each (`catalog/backend-laravel.md`, "The strictness calls"); the list of counted model-strictness violations is gone.

**Tests**
- Zero skipped or todo tests. Test helpers, fixtures, doubles and file locations follow the test conventions, each with a guard.
- Every test that boots the application starts from the hermetic base set-up, and the architecture presets pass with no exception (`catalog/tests-database-ci-docs.md`, "Hermetic base set-up" and "Architecture presets").
- Line coverage of the backend source is at least 95, and so is that of the frontend's logic; the frontend's components are at their measured floor. Each number is in a committed file, starts at the measured value in step 2 and only rises, held by the `min` ratchet rows `coverage-floor`, `frontend-logic-coverage-floor` and `frontend-coverage-floor`; `--final` fails the first two below 95. Type coverage is 100 (`catalog/tests-database-ci-docs.md`, "Coverage").
- A mutation score that cannot fall: a number in a committed file, held by the `min` ratchet row `mutation-floor` (`04-guards.md` C2).
- Every behavior class has a test that names it. The mutation run covers the whole tree in the scheduled job and, from step 9 on, the changed files of every pull request.
- Every external HTTP client and every API a sibling repository calls has a contract fixture test.

**Dependencies and CI**
- Dependency audits block and are at zero, with an empty ignore list. The one way past a fresh advisory is a time-boxed exception: a line in `scripts/audit-exceptions.json` with a reason and an end date at most 7 days after the day it was added, read by `scripts/audit-gate.sh`; an expired line fails the audit and is never renewed. A package version with a known advisory cannot be installed (the row `advisory-block`), the manifest's lower limits are the installed versions, and the dependency bot runs on the kit's config (`catalog/tests-database-ci-docs.md`, "CI and dependencies"). No package a major version behind, unless the owner pinned it (its decision row, under "Shrink-only rows"). Minor and patch updates are the dependency bot's work: the row `dependencies-behind` is shown and never fails.
- The commit-message check, the ratchet script and the dashboard run on every PR, and the dashboard runs with `--final` (`04-guards.md` C3). The scheduled job runs the full dashboard, so the rows a pull request skips gate too.
- The formatter checks the whole tree; the manifest of every package manager validates.
- The secret scan, the smoke suite (in a browser where the project has a frontend), the one local command equal to CI (`scripts/checks.sh`, which `composer test` calls in a PHP project, beside the five other check script names every PHP project has, and the update script), the contract test over the CI files (`04-guards.md` C4) and the object-level access sweep are in place (`02-step-1-discover.md` 1.7).

**Docs**
- `AGENTS.md` files describe the final state. Finished plans are archived. No doc claims something the gates do not enforce, and every path a guide names exists.

**Runtime**
- A dashboard at target does not prove that nothing is broken: every step that changed source code ended with the runtime check on a production-like environment (`05-program.md` D4, point 5).

**Shrink-only rows.** This is the one place that says which rows may stay above zero; other files point here. Zero means zero for every counter except the rows named in `SHRINK_ONLY`, in the config block of the dashboard script. `--final` does not fail those, and the program may close with them:

- three are fixed: the ratchet rows `comments`, `test-doubles` and `event-fake-all`. They only go down. The row `comments` is the one of the three that takes an override at 0 (`OVERRIDE_AT_ZERO` below): a rise needs the override tag with a reason a reviewer reads (`04-guards.md` C6);
- anything else enters `SHRINK_ONLY` only as a counted, dated, owner-approved decision row in the plan: the evidence why zero is impossible now, the step or date by which the row reaches 0 (an end date; a row without one is refused), and the owner's approval, asked at the next stop of the lead (`05-program.md` D4); the lead alone never decides that a row stays above zero. A ratchet row enters by its name, a cap as `cap-<key>` (its dashboard row is then held to the cap, not to zero). When the date passes with the row above zero, the row is open debt again and the program is not done. Zero means zero: there is no other exception. Two categories need no end date, because neither is debt: `lint-config-ignores` (an `ignores` entry that scopes a rule to one kind of file, or that names generated output) and `lint-config-off` (a core rule that is off because its typed replacement is on). Both stay. Every other entry needs an end date. A major version the owner pinned is such a row too: its decision row puts `cap-dependencies-majors-behind` in the list, with the number of pinned majors as the cap and the end date of the pin. A `max` row at 0 takes no override, except the rows named in the config value `OVERRIDE_AT_ZERO` of the ratchet script (default `comments,cap-dependencies-majors-behind,audit-exceptions`): a justified comment, a major pinned after the cap reached 0 and a dated audit exception still need the tag and a reason a reviewer reads. This includes a cap: a cap at 0 is never raised, so a project does not add a name to that value without a decision row. What the guard enforces is that a counter may only go down and that every override is a tagged commit; the reading of the tag is a code owner's. The edit to `SHRINK_ONLY` changes a guarded script (`04-guards.md` C2), and the reason of its tag names the decision row. The auditor reads every such entry against its row (`09-reviewer-brief.md`, auditor item 1). Every other row and every other cap is 0.

Decision lists are answered before the program closes: each site is decided, by the lead with evidence, or by the owner where behavior changes (`04-guards.md` C7).

**And then:** two fresh auditors in a row, each an agent that built none of it, add no counter of a kind in the signed definition (`06-lock-and-audit.md` E2).
