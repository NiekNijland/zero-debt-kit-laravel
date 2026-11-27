# zero-debt-kit-laravel

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Green CI does not mean a codebase has a shape. You can have high coverage and PHPStan at level 8 and still end up with three ways to authorize a route, four ways to write a test helper and a new pattern with every pull request. A linter checks lines, not structure. With AI agents writing a growing share of the code, the drift gets faster.

This kit makes CI the quality gate for structure. It does that with two mechanisms:

- **Architecture tests** decide, per task, the one allowed way: where a class lives, what it may depend on, how a controller or a test is shaped. Code that does it another way fails the build, with a message that says what to do instead.
- **Ratchets** are counters for everything that is still wrong: old violations, suppressions, skips, oversized files, baselines. A counter may only go down. A rise needs a commit with a tag and a reason that a code owner reads, and at zero the list is deleted and CI fails if it comes back.

Together they are the guards. The result: anyone, including an agent working unattended, can contribute, and when CI is green the code fits the patterns the guards check. A guard only checks what its tests show it can see, and review covers the rest.

## What the gate looks like

A class in a place the layout table does not allow. The architecture test names the rule, the place and the fix:

```text
FAIL  keeps every class of a module in the folders of the module table of app/AGENTS.md
place app/Modules/Orders/Loose.php: no folder of the module table in app/AGENTS.md holds a class here.
A class sits in <Domain>/<Folder>/[<Subdomain>/]<Class>.php, never in the module root or a folder the
table does not name, as the table says.
```

A suppression added to make a static-analysis error go away. The ratchet row is at zero, so the build fails, and a row at zero takes no override (three rows are named exceptions, below):

```text
ratchet                                 may    base    head  change  result
analyser-baseline                      down       0       0      +0  ok
analyser-ignore                        down       0       1      +1  FAILS

A ratchet moved the wrong way, or a row was redefined or removed. A row that is at 0 takes no override, except the rows of OVERRIDE_AT_ZERO: fix the entries. Other rows can be explained in a commit that touches that row's files, or this script for a redefined or removed row:
  [ratchet: <name>] <reason>
A tag counts only at the START OF A LINE of a commit message: the subject line counts, the middle of a line does not. A squash keeps only the tags written in the squash message: list every [ratchet: ...] line again.
```

The first output is the message of a guard of the starter (`reference-project/README.md`); the second is what `tools/ci-ratchets.sh` prints when a suppression is added (the numbers are illustrative).

## What it is

A program you hand to an AI agent in your repository. It measures the codebase, writes down the one way per task (with a file to copy), puts a guard in CI for every rule, and burns the old way down to zero in ten steps. You sign off at three gates and answer the questions that are yours; the agent does the rest. The end state: every task is done one way, the way is written down, a guard stops the old way coming back, every debt counter is at zero, and nothing lets a counter grow again.

It is one standard: there is no lighter version of done. A reader who only wants a first guard has the path below; it is a way in, not a smaller finish.

## Minimal adoption: the ratchet script and three guards

You do not need the program to get the core effect. Two parts carry most of it: a counter that cannot grow, and a few architecture tests that fail with a message.

1. **The ratchet script.** Copy `tools/ci-ratchets.sh`, `tools/kit-config.sh` and `tools/debt-caps.json` into `scripts/` of your repository (the scripts are called with `bash`, they carry no executable bit). Fill in the config block at the top of `ci-ratchets.sh` (your source, test and frontend folders, the config files of your tools) and the base branch in `kit-config.sh`. Run `bash scripts/ci-ratchets.sh --head` to see every row's value today, and add `bash scripts/ci-ratchets.sh` as a blocking step of your pull-request pipeline. Run `bash scripts/ci-ratchets.sh --self-test` once: it proves each row counts the spellings it must. From then on no counter can grow; the rows are listed under "Every ratchet" and explained in `tools/README.md`. It needs `bash` 3.2 or later, `git`, and `jq`, `node` or `php` for the rows that read JSON.
2. **Three guards from the starter** (`reference-project/files-to-copy.md` lists the paths; they were chosen because they need the least around them, and the PHP guards need the PHP parser package `nikic/php-parser`):
   - the guard over the guides: `tests/Support/Architecture/DocumentedPathGuard.php` with `tests/Unit/DocumentedPathArchitectureTest.php` (every path a guide names exists);
   - the rules guard: `tests/Support/Architecture/GuideRulesGuard.php` with `tests/Unit/GuideRulesArchitectureTest.php` (the guide must keep saying what a rule says);
   - the size guard: `tests/Support/Architecture/FileSizeGuard.php` with `tests/Unit/FileSizeTest.php`.

   They share the helpers `tests/Support/Narrow.php`, `tests/Support/RecursiveFiles.php` and `tests/Support/SourceSandbox.php`; the size guard also reads source through `tests/Support/Architecture/PhpFileInspector.php`, `PhpSource.php` and `PhpCallReader.php`. The shape of every guard (a detector class, a thin test with failing examples, passing examples and one case over the real tree) is `04-guards.md` C1.

What you do not get this way: the layout, the written patterns, the burn-down, the end state, the guard over the pipeline and the review that keeps the guards honest. A project that stops here has a gate, not a finished cleanup.

## Who it is for

Teams on Laravel and React (Pest, PHPStan, Rector, Pint) that already have decent coverage and static analysis and still see patterns drift, especially when agents write code. The kit is for a Laravel (PHP) backend with a React frontend; a Laravel project without a frontend source folder is supported too. Other stacks are out of scope: the method can inspire them, but nothing is promised or proven.

The kit decides the patterns and the structure, and only techniques differ between projects (`01-principles.md`, "Who decides what"). It is generic: it holds no organisation-specific rule, name, address or token. What an organisation manages for itself (a shared formatter config, shared pipeline images, an autofix commit) is asked in step 1 and stays the organisation's.

## Every guard

One mechanism per kind of rule (`04-guards.md` C1 and the tables of `catalog/`). The table says what each kind forbids; "the reference" is the worked example: the running starter, <https://github.com/NiekNijland/zero-debt-laravel> (`reference-project/README.md`).

| Kind of guard | What it forbids, and why it matters |
| --- | --- |
| Banned-call rules (analyser and linter, with a message that names the replacement) | A call that has one allowed place or none (an environment read outside config, a raw HTTP client in a domain class, an event helper). Without the rule every author picks their own way. |
| Analyser switches | The strictness the analyser already has (override markers, unchecked indexes, too-wide return types) being off. The switch is cheaper and sharper than a hand-written guard. |
| Architecture tests of class shape | A class with the wrong suffix, the wrong parent, a missing `readonly`, or a dependency on a layer it may not use. Shape drift is what makes a codebase unpredictable. |
| Layout guard | A class outside the folders of the layout table, or named against it. It is generated from the table in the guide, so the guide and the tree cannot disagree. |
| Size guards | A file or a method over the limit (400 lines, 80 for a method, 40 for a controller action), and the same for tests and for the frontend. Big units hide several responsibilities. |
| Guards over what the analyser cannot see | Test files, comments, call shapes across files and SQL in migrations that break a rule. They run on one shared source reader, so there is one parser and not twelve regexes. |
| Schema tests | A column type, a foreign key without an index, or a migration shape that a later release cannot undo, checked against the migrated schema. |
| Route coverage and coverage over any list the application can print | A route, console command, component action or channel that no test hits or that skips a rule. The test asks the application for the list, so a new member is covered the moment it exists. |
| Object-level access tests | A route that checks whether a user may do something but not whether the requested record is theirs. One data-driven test asks the application for every entry point that takes a record id, builds a record that belongs to someone else, and expects a refusal. |
| Register guards | A class, event or queue payload name stored in data that is not on a sorted register, or a listed name that is gone without an alias. It protects queued jobs and rows across a class move. |
| Contract test over the CI files (22 points, below) | A pipeline that narrows: a check that skips, a step with no time limit, an unpinned image, a clone too shallow to compare, a missing code-owners rule. |
| Guide guards | A guide that names a path that does not exist, or a row of the one-pattern table that is missing from the review rubric. Docs that claim what no gate enforces are debt. |
| Rules guard | A guide that loses the words a rule must hold. One table of rules, each with an id and what to do instead, checked in two directions. |
| Guard over the guards | A guard test without failing examples, passing examples and a real-tree case, or a guard that holds a literal path. A blind guard is worse than none because it reads as proof. |
| Frontend lint rules | Restricted syntax, imports and properties, typed rules and a small local plugin, with every settled rule named as `error` so a preset that changes cannot drop it. Layers (types, lib, hooks, components, pages) import only downward. |
| Formatter rules | Style that changes behavior if left open: strict types, strict comparison, closed classes, immutable dates, multibyte string functions, private members. The formatter is held at 21 rules by a ratchet row. |
| Refactoring-tool sets | The mechanical cleanups (dead code, code quality, type declarations, framework sets) being switched off. 18 sets are held by a ratchet row. |
| Unused code | An unused export, file, public method, class or package. The detector runs on the whole tree with no ignore list. |
| Type coverage | A parameter, return or property without a type. Held at 100. |
| Coverage floor | Line coverage that falls. The floor is a number in a committed file, raised to the measured value at the end of every step, never lowered; the end goal is 95 for the backend and the frontend logic. |
| Mutation floor | Tests that pass but cannot fail. A mutation score held at a committed number; the mutation run covers the whole tree in the scheduled job and the changed files of every pull request from step 9. |
| Break sample | A review step: the reviewer breaks the code in a few places and expects a red test. Written in `09-reviewer-brief.md`. |
| Secret scan | A token-looking literal in any commit of the range (gitleaks over every commit between the base and the head). |
| Audit gate | A dependency with a known advisory. Audits block, an exception is one dated line that expires in at most 7 days and is never renewed, and a package version with a known advisory cannot be installed. |
| Commit-message check | A commit that does not start with the ticket and a type, so a history can be read and filtered. |
| Hermetic and tier tests | A test that boots more of the application than it needs, shares a database with another test, reads the real clock or reaches private state. |
| Smoke suite | A deploy that boots with the wrong drivers or a broken seed. Five steps against a stack booted with the production drivers, before every deploy. |
| Config tests | A runtime fact that drifted (a driver default, a strictness switch, a time zone): one assertion per fact, read from the booted application. |
| Generated files and manifests | A generated type file that differs from its source, or a manifest whose lower limits are not the tested versions. CI regenerates and fails on a diff. |
| Candidate guards | A rule the review rubric holds as review only. It is counted (`04-guards.md` C7) and gets a guard when the count allows it. |

### The contract test over the CI files, in 22 points

`04-guards.md` C4 lists what the test asserts; in short:

1. five runs (checks, checks on a deploying branch, tests, smoke, scheduled), each on its trigger, with no path filter on the checks;
2. every part of the checks has a step with no condition, a full-history clone, and the base branch set;
3. the checks script runs its four stages in order and holds no placeholder;
4. every guard test file is in the guard group;
5. every check names the whole tree: no list of changed files;
6. nothing writes, commits or pushes, except an owner exception named in the profile;
7. every image, action and downloaded tool is pinned;
8. every step has a maximum time;
9. no manual trigger on a check, and nothing is allowed to fail;
10. the smoke run boots the production drivers and the database image equals the production engine;
11. installs come from the lock file;
12. the type-aware lint run has no content cache;
13. the scheduled run calls the full dashboard and the whole-tree mutation run;
14. the coverage floor is held in the test run, and the mutation run is wired from step 9;
15. the analyser paths name every source and test folder and include the bleeding-edge file;
16. the task runner scripts (`composer.json` scripts) keep their six names and the strict-model check stays wired;
17. a frontend project's smoke run installs a browser;
18. every step that reads history clones it in full;
19. a branch runs each suite once before its deploy;
20. the default base branch is the one pull requests target;
21. the audit of a deploying branch covers production dependencies only;
22. the files that carry the guards are covered by a code-owners rule that names an owner.

The starter holds a GitHub reader; the Bitbucket and GitLab readers of the earlier extract are no longer shipped (`docs/starter-variants.md`), and the skeletons of all three hosts are proved on examples (`tools/proof/fixtures/ci/`); `04-guards.md` C4 says which points each covers.

## Every ratchet

A ratchet row is a name, a direction and a method that counts something in the tree. `tools/ci-ratchets.sh` counts every row at the merge base (with the base's own definition) and at the head. A `max` row may only go down, a `min` row only up. A wrong-way move fails unless a commit that touches the row's files carries `[ratchet: <name>] <reason>`; a `max` row at 0 takes no override, except the rows named in `OVERRIDE_AT_ZERO` of the script (default `comments,cap-dependencies-majors-behind,cap-dependencies-abandoned,audit-exceptions`). A row that is new in a pull request passes, which is how a guard arrives with its counted list. The start set below, 23 names plus one `cap-<key>` row per key of the caps file, is the same in every project (`04-guards.md` C2, `tools/README.md`, "The rows that ship"); `--self-test` runs 382 cases that prove every regex row counts the spellings it must and ignores the near misses.

### Rows that ship (the start set)

| Row | Direction | What it counts | Why |
| --- | --- | --- | --- |
| `analyser-baseline` | max | entries of the analyser's baseline and `ignoreErrors` | A baseline is a list of known errors; it may only shrink and is deleted at zero. |
| `analyser-ignore` | max | inline analyser ignores and `excludePaths` | An ignore is a baseline entry that hides. |
| `analyser-level` | max | analyser configs below the top level | The analyser runs at its top level on source and tests. |
| `analyser-switches` | min | strictness switches that are on (7 PHP, 11 TypeScript: 18) | A switch removed or set to false must fail; a `max` row cannot count what is missing. |
| `refactor-skip` | max | skip entries of the refactoring tool | A skip is an exception to the cleanup. |
| `refactor-sets` | min | refactoring sets that are on (18) | A set switched off lets the old way return. |
| `formatter-rules` | min | strictness rules of the formatter that are on (21) | A rule removed or set to false must fail. |
| `advisory-block` | min | 1 when the manifest requires the advisory-blocking package | A vulnerable version cannot be installed. |
| `audit-ignore` | max | advisories the package manager is told to ignore | An ignored advisory is an unfixed one. |
| `audit-exceptions` | max | lines of the dated audit-exceptions file | Each line is time-boxed to 7 days and never renewed. It takes the override at 0. |
| `test-skip` | max | skipped, todo and incomplete tests in the backend test framework | A skipped test is a test that does not run. |
| `test-doubles` | max | mocking-library doubles of the project's own classes | A test with a double of the project's class tests the double. Shrink-only, so a rise needs the override tag. |
| `event-fake-all` | max | event fakes with no list of events | It silences every listener. Shrink-only. |
| `lint-disable` | max | lint disable comments | A disabled rule is a hole in the whole-tree check. |
| `lint-config-off` | max | rules at `off` or `warn` in the lint config | A rule that is not `error` does not block. One category may stay (a core rule off because its typed replacement is on). |
| `lint-config-ignores` | max | entries of `ignores` lists in the lint config | An ignore is an exception. A category may stay (generated output, a rule scoped to one kind of file, prototypes the owner keeps on purpose: `01-principles.md`, "Kept prototypes"). |
| `lint-baseline` | max | the linter's bulk suppressions | Counted per entry, so it shrinks. |
| `type-baseline` | max | lines of every keyed baseline (type-checker flags, unused-code tool) | One line is one finding; a file that grows fails. |
| `ts-suppressions` | max | `@ts-expect-error`, `@ts-ignore`, `@ts-nocheck` | Type escapes. |
| `frontend-test-skip` | max | `skip`, `todo`, `only`, `fails` and `xit` in the frontend tests | A focused or skipped test hides the rest. |
| `comments` | max | comment lines in source, tests and frontend | "No comment unless a future reader would act differently" cannot be a yes or no test; the count only goes down. Shrink-only; it takes the override at 0. |
| `todo` | max | `TODO`, `FIXME`, `HACK`, `XXX` | An open loop belongs in the backlog. |
| `ci-skipped-steps` | max | "succeed anyway" tails, `continue-on-error`, `allow_failure` in the CI files | Nothing may hide a failure. |

The caps file ships with 13 keys, so 13 more rows:

| Row | Direction | What it counts | Why |
| --- | --- | --- | --- |
| `cap-source-files-over-limit` | max | the cap of source files over 400 lines | A cap only goes down; there is no row over the sum, so one cap cannot rise by what another falls. |
| `cap-frontend-files-over-limit` | max | the cap of frontend files over the limit | same |
| `cap-test-files-over-limit` | max | the cap of test files over the limit | same |
| `cap-open-classes` | max | the cap of concrete classes still open for extension | same |
| `cap-classes-no-test-names` | max | the cap of behavior classes no test names | same |
| `cap-classes-without-own-test` | max | the cap of behavior classes without a test file of their own | same |
| `cap-support-classes-without-own-test` | max | the cap of support classes without a test file of their own | same |
| `cap-frontend-logic-without-test` | max | the cap of frontend logic files without a test | same |
| `cap-frontend-components-without-test` | max | the cap of frontend components without a test | same |
| `cap-docs-older-than-limit` | max | the cap of docs past the age limit | same |
| `cap-dependency-advisories` | max | the cap of dependency advisories | same |
| `cap-dependencies-abandoned` | max | the cap of abandoned packages | same |
| `cap-dependencies-majors-behind` | max | the cap of packages a major version behind | A pinned major is a decision row; it takes the override at 0. |

### Rows the project adds under a fixed name

Same name in every project (`tools/README.md`, "Adding a row"; `04-guards.md` C2):

| Row | Direction | What it counts | Why |
| --- | --- | --- | --- |
| `coverage-floor` | min | the committed backend coverage threshold | Coverage must not fall; the goal is 95. |
| `frontend-coverage-floor` | min | the committed threshold of the whole frontend | Held at its measured floor. |
| `frontend-logic-coverage-floor` | min | the committed threshold of the frontend's logic folders | The goal is 95. |
| `mutation-floor` | min | the committed mutation score | Tests must be able to fail. |
| `strict-types-missing` | max | PHP files without the strict-types line | Coercion at every call, until the formatter rule lands. |
| `loose-comparisons` | max | `==` and `!=` sites | Counts the sites of the strict-comparison rule before it goes on. |
| `mutable-dates` | max | `DateTime` and `Carbon` sites | Counts the sites of the immutable-date rule before it goes on. |
| `byte-string-functions` | max | byte-string function calls | Counts the sites of the multibyte rule before it goes on. |
| `queue-bus-fake-all` | max | bare queue and bus fakes in tests | A fake with no list hides what is dispatched. |
| `model-strictness-violations` | max | lines of the counted list of strict-model violations (step 4) | The list is emptied in step 8. |
| `guide-future-tense` | max | "will", "for now", "until" in the guides | A guide describes the final state. |
| `unused-ignore` | max | entries of the unused-code tool's ignore list | An ignore list is an exception list. |
| `cap-autoload-conformance` | max | the cap of classes whose namespace and name do not match their path | A class the autoloader skips is only logged. |
| `cap-ci-duration-minutes` | max | the cap of pipeline minutes | Only the host knows the number. |

### Dashboard rows that are not ratchet rows

`tools/debt-dashboard.sh` prints every counter with its target, and is a blocking step right after the ratchets.

| Row | What it counts |
| --- | --- |
| `source-files-over-limit`, `frontend-files-over-limit`, `test-files-over-limit` | files over the line limit (400) |
| `open-classes` | concrete classes still open for extension (the counter of the formatter's closed-class rule) |
| `classes-no-test-names` | behavior classes that no test file imports or names |
| `classes-without-own-test`, `support-classes-without-own-test` | classes without a test file named after them |
| `frontend-logic-without-test`, `frontend-components-without-test` | frontend files without a `<file>.test.*` next to them |
| `docs-older-than-limit` | docs whose last change is older than the age limit (180 days by default); skipped in the gate on a shallow clone with the reason printed, and `UNKNOWN` in `--final` |
| `dependency-advisories`, `dependencies-abandoned`, `dependencies-majors-behind` | the package audit and the packages a major behind; one command each |
| `dependencies-behind` | packages a minor or patch version behind: shown, never fails (the dependency bot's work) |
| `files-outside-row-paths` | tracked source files that no row counts: must be 0 in both modes |

### Caps, the end state and the guarded files

- **Caps.** A row that cannot be at zero yet gets a cap in `debt-caps.json`, one key per row, never a number inside a script. A cap only goes down. A cap at 0 is never raised, except `cap-dependencies-majors-behind` and `cap-dependencies-abandoned` (`OVERRIDE_AT_ZERO` rows); above 0, raising one needs `[ratchet: cap-<key>] <reason>`, so new debt of a kind whose cap is 0 is fixed, not capped. The lead lowers every cap to its row at the end of every step, and `--final` fails a cap above its row.
- **`--final`, the end state.** `debt-dashboard.sh --final` is the exit of step 10 and the scheduled run after it: every row at zero, no cap above its row, `analyser-switches` at its target, and every `min` row with a goal at its number (coverage at 95, 18 refactoring sets, 21 formatter rules, the advisory block). The only rows that may stay above zero are the shrink-only rows: `comments`, `test-doubles`, `event-fake-all`, and rows the owner approved as a dated decision row.
- **Guarded files and the override tag.** The other scripts, `kit-config.sh`, the pipeline files of the three hosts and `.gitattributes` are guarded files: a change needs `[ratchet: script] <reason>`, and `ci/checks.sh` first runs the base's copy of the ratchet script, so a pull request cannot rewrite its own gate.
- **The code-owners rule.** The tag is written by the author, so only a person can tell a real reason from one written to get past a guard. The files that carry the guards are covered by a code-owners rule that names a person or a team, and the host requires that review (`ci/CODEOWNERS.example`, `04-guards.md` C2 and C4 point 22, `07-process.md` section 12). The setting itself is the owner's and lives on the host, not in the repository.

## Install as an agent plugin

The kit is also a Claude Code plugin: the skills start and continue a run, review a guard or a pull request, and check a project's copy of the scripts. They point at the numbered files of this kit; they do not copy them.

```text
/plugin marketplace add NiekNijland/zero-debt-kit-laravel
/plugin install zero-debt@zero-debt-kit
```

Then, in the target repository:

| Skill | Use |
| --- | --- |
| `/zero-debt:zero-debt <TICKET>` | start a run: the step 1 questions, then the program (`00-START-PROMPT.md`) |
| `/zero-debt:zero-debt-continue <TICKET> <hand-off folder>` | continue a run from `CONTINUE.md` |
| `/zero-debt:zero-debt-review <branch, PR or guard>` | cold review with `09-reviewer-brief.md` |
| `/zero-debt:zero-debt-doctor` | run `tools/doctor.sh` against a kit checkout and explain the result |

The plugin files validate locally with `claude plugin validate .`; installing from the marketplace has not been tested yet. The skills are plain `SKILL.md` files, so another agent can read them from `skills/`. Without a plugin, give the agent the folder path and paste `00-START-PROMPT.md` as "How to use it" says. Plugin instructions run with your agent's privileges: pin to a release tag (v0.12.0 once tagged) or a commit hash and read what you install (`SECURITY.md`).

## How to use it

1. Give the agent this folder's path. Start a session in the target repository and paste `00-START-PROMPT.md`.
2. The agent asks the questions of step 1 that it cannot answer from the repository, then measures, maps the catalog onto the project and audits. No code changes. You sign the definition of clean and answer every question that is yours, all in step 1.
3. The agent works through steps 2 to 10 of `05-program.md` and stops for your go after steps 1, 3 and 10, or when a decision is yours. A step that changes source code ends with a runtime check on a production-like environment.

Step 1 changes no tracked file. It produces four files: a project profile, a measured plan with a dashboard of every counter, a backlog and a hand-off note.

More: `docs/adoption-guide.md` (who it is for, what to prepare, what the three gates ask of you), `docs/faq.md` (how it differs from Deptrac, PHPArkitect, Pest arch, Betterer and SonarQube), `CHANGELOG.md` and `UPGRADING.md` (versions and how to move a project's copy), `tools/doctor.sh` (compares a project's scripts with the kit), `CONTRIBUTING.md`, `SECURITY.md`.

## The ten steps

1. **Discover and define**: ask, measure, map the catalog onto the project, audit, and sign the definition of clean. No code changes.
2. **Make the checks tell the truth**: install the scripts and the pipeline, turn every check to its final strictness on the whole tree, and make it green with counted baselines.
3. **Freeze the patterns**: write the one-pattern table, layout table, test conventions and rubric, and add every guard with its counted list.
4. **Test foundation**: dead code first pass, one test framework, the hermetic base set-up, test conventions and the flake sweep.
5. **Layout**: move every class to the place the layout table says, by a move tool from a map, with the lists rewritten in the same commit.
6. **Types**: typed config, the analyser baseline to zero, the frontend type escapes to zero.
7. **Module hygiene**: pure layers, splits by responsibility, one module per domain.
8. **Patterns**: converge each theme (validation, data access, errors, responses, bootstrap, runtime strictness, text and bytes) to its one way, behind its guard.
9. **Fill and trim**: coverage to 95, missing tests that can fail, contract fixtures, the mutation run on changed files.
10. **Lock and audit**: delete every list, repeat the review-only sweep, audit with fresh agents, run `--final`, and do the runtime check on the final commit.

## What is proven and what is not

- **The scripts are proven by a proof suite.** `tools/proof/proof.sh` builds scratch repositories and runs more than 600 scenarios against the scripts in `tools/` and `ci/` (it counts scenarios, not features), and `ci-ratchets.sh --self-test` runs 224 regex cases. The proof runs no real analyser, formatter, refactoring tool, coverage tool, unused-code tool or CI host; it replaces the secret scan by `true`.
- **The program has been run on three Laravel applications, all hosted on Bitbucket.** The Bitbucket skeleton ran in real pipelines there, and the lessons of each run are written back into the kit. Only a few measured figures appear in the method files, each marked as one run; the rest of what the runs measured is not published here.
- **GitHub Actions has run once.** The GitHub skeleton ran on one real repository (a private repository of a free plan, runner form). What was seen and what was not is in `04-guards.md` C4, "What has run". Treat the rest of the GitHub skeleton (the container form, the schedule, a stacked pull request, a required check) as unverified (`docs/adoption-guide.md`, "GitHub first-run notes").
- **GitLab is untested.** The skeleton exists and is checked against examples only.
- **Mutation tools.** The Pest, Infection and Stryker forms were run on scratch projects on 2026-11-26 (Pest 5.3.7, Infection 0.35.12, Stryker 10.1.1); the versions and the recorded outputs are in `tools/README.md`, "Mutation". The proof replays those outputs with a fake `npx` and fake `vendor/bin` scripts. Only the Pest form was also run on a sample of a real application. Not verified: Infection with Xdebug or phpdbg, a Stryker path that holds a comma, and a Stryker runner other than vitest.
- **The reference is a starter, not a product**: a small running application (a notes API) built at the kit's end state; what it lacks (multi-tenancy, a frontend, the Bitbucket and GitLab readers) is in `docs/starter-variants.md`.

## About the reference

The worked example is the strict Laravel starter, <https://github.com/NiekNijland/zero-debt-laravel>: a running Laravel application with the guards, configs, scripts and guides of the kit, and a nightly canary against the kit's `main` (`.github/workflows/starter-canary.yml`, a proposal: not run yet). `reference-project/` is the pointer to it: `README.md`, `files-to-copy.md` (the starter's paths and what each is for) and a few examples that do not run. The starter is pinned to a kit commit and lags the kit; where they differ, the kit wins.

## Files

| File | What it is | Read when |
| --- | --- | --- |
| `00-START-PROMPT.md` | the prompt to paste | by the owner |
| `01-principles.md` | the goal, who decides what, the rules that never bend, the definition of done | first |
| `02-step-1-discover.md` | step 1: ask, discover, map, measure, audit, define clean | first |
| `03-conventions.md` | the way per task; the documents to write; the layout; decisions | in step 1 (mapping) and step 3 (documents) |
| `04-guards.md` | one mechanism per kind of rule, with the standing list of blind sides; the ratchet; the dashboard; CI shape; baselines; candidate guards | C1, C2, C3 and C6 in step 1; all of it in steps 2 and 3 |
| `05-program.md` | the ten steps with their exits, PR shape, end-of-step checks, size, closing classes, moving classes in three releases | D1, D2 and D6 in step 1; all of it before step 2; then, at the start of each step, that step's part of D2 and the catalog tables it names |
| `06-lock-and-audit.md` | deleting lists, the audit and its end rule, keeping zero, closing the program | step 10 |
| `07-process.md` | roles, lifecycle of a change, comments, conflicts, stacking, reading a red step, who owns the guard files | by the lead, before the first code change |
| `08-builder-rules.md` | rules for an agent that builds one theme | by a builder, with its brief and nothing else |
| `09-reviewer-brief.md` | cold reviewer with the break sample, object-level access checklist, auditor | reviewers and auditors |
| `10-hazards.md` | the traps, each ending in what to do | before the first code change |
| `record-source-project.md` | optional reading: the lessons of the earlier runs and the instruction each became | never needed for a run |
| `catalog/` | `backend-laravel.md`, `frontend-react.md` and `tests-database-ci-docs.md`: per task the one way, how to count the old way, and the guard | in step 1 (the mapping of 1.5) only the files of the project's stack: `backend-laravel.md` for a Laravel backend, `frontend-react.md` for a React frontend, `tests-database-ci-docs.md` with either. Then per step, the tables that step names |
| `templates/` | `project-profile.md`, `zero-debt-plan.md`, `backlog-and-continue.md`, `repo-docs.md` (the `AGENTS.md` set, decision record, review rubric, PR template), `pr-description-and-brief.md` (with the move map format), `tool-configs.md` (formatter and refactoring-tool configs to copy) | when creating them |
| `tools/` | `ci-ratchets.sh`, `debt-dashboard.sh`, `debt-caps.json`, `kit-config.sh`, `ci-commit-messages.sh`, `ci-parallel.sh`, `keyed-baseline.sh`, `audit-gate.sh`, `mutation.sh`, `map-normalise.sh` and `move-ts.mjs` (what each does: `tools/README.md`), and `tools/proof/proof.sh`, which proves every script in scratch repositories. The starter holds a copy of each of them with its own config block | step 1 (the scratch dashboard of 1.6, the mutation sample of 1.7) and step 2 |
| `ci/` | `checks.sh`, the one command that the pipeline's checks step (one step per part) and a developer both run, `prepush.sh`, which runs every part side by side before a push, `CODEOWNERS.example`, and the pipeline skeletons `bitbucket-pipelines.yml`, `github-actions.yml` and `gitlab-ci.yml`; a repository that has a pipeline adds their jobs to its own file | step 2 |
| `AGENTS.md` | instructions for an agent that maintains the kit itself | when changing the kit |
| `.claude-plugin/`, `skills/` | the Claude Code plugin and marketplace manifests and the four skills | when installing or changing the plugin |
| `docs/` | `adoption-guide.md`, `faq.md`, `launch-post-draft.md` | before adopting |
| `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md`, `VERSION`, `CHANGELOG.md`, `UPGRADING.md` | how to contribute, report a vulnerability, behave, and which version a copy is | when contributing or upgrading |
| `reference-project/` | the pointer to the strict Laravel starter (the worked example), the list of files to copy from it, and a few examples that do not run. `docs/starter-variants.md` is the backlog of what the starter lacks | when building a guard: the `README.md` first |

## Limits

- No dates, estimates or priorities: order comes from dependencies.
- It does not decide product questions and does not touch the ticket system.
- The starter is a project's end state. Copy from it by `reference-project/files-to-copy.md`, after the starter's `KIT-FINDINGS.md`; never paste a guard that names classes the target does not have.
- The scripts in `tools/` name no project: each starts with a config block the project fills in. What each needs on the runner and on a developer machine is in `tools/README.md`.
- The kit is self-contained: nothing outside this folder is needed.
- The PHP floor is 8.4 (`catalog/backend-laravel.md`, "Version floor per row").

## License

Released under the MIT License (see `LICENSE`).
