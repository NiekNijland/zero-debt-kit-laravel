# Tools

Ten scripts and tools, the config file they share and a caps file. They name no project: each script starts with one config block, and everything a project sets is in that block, in `kit-config.sh` or in the table of rows. The skeletons that call them are in `ci/`. The reference project's originals of the ratchet script, the dashboard, the commit-message check and the parallel runner are in `reference-project/scripts/`. The scripts carry no executable bit: every call is `bash <file>`, in the pipeline too.

How they get into a repository, in which order, and how they meet a pipeline that already exists: `05-program.md` D2, step 2. In the target repository they live in `scripts/`.

| File | What it does |
| --- | --- |
| `ci-ratchets.sh` | fails a pull request when a counter moves the wrong way between the merge base and the head |
| `debt-dashboard.sh` | prints every counter with its target; the gate of the checks step |
| `debt-caps.json` | the cap of every dashboard row, one key per row |
| `kit-config.sh` | the values more than one script reads; each script sources it from the folder it is in |
| `ci-commit-messages.sh` | fails a pull request with a commit that does not start with the ticket and a type |
| `ci-parallel.sh` | runs named commands side by side, with a time limit, and reports each |
| `keyed-baseline.sh` | the baseline for a tool that has none of its own: one line per finding, compared with a committed file |
| `audit-gate.sh` | the dependency audit with time-boxed exceptions: reads the audit's JSON, prints the number of advisories no active exception covers plus the broken entries, exits 1 above 0 |
| `mutation.sh` | the mutation run of the stack's tool, whole tree or the changed files, with the minimum score read from the committed floor file; the pipeline calls it as a script (an inline command with `": "` in a YAML list item parses as a map) |
| `map-normalise.sh` | maps the names in a text back through the move map (or forward), longest name first, doubled backslashes too: the lists of an old and a moved stack can then be diffed |
| `move-ts.mjs` | moves TypeScript or JavaScript files from a map and rewrites every specifier (relative, alias, `import()`, mocks); `--verify`, `--reverse`, idempotent. Copied to `tools/codemods/`, which `UNCOUNTED_PATHS` names |
| `proof/proof.sh` | proves every script in scratch repositories (see "Proof"); it stays in the kit |
| `ci/checks.sh` | the one command: CI runs it in the checks step, one pipeline step per part (`--part <name>`), a developer runs it before a push |
| `ci/prepush.sh` | runs every part of `checks.sh` side by side in one place, before a push, so CI only confirms |
| `ci/bitbucket-pipelines.yml`, `ci/github-actions.yml`, `ci/gitlab-ci.yml` | the pipeline per host, with the five runs of `04-guards.md` C4 |

## Requirements

- `bash` 3.2 or later (the version macOS ships) and `git`. No GNU-only tool: no `timeout`, no `wait -n`.
- `grep`, `awk`, `sed`, `sort`, `comm`, `wc`, `tr`, `xargs`, `mktemp`: the POSIX ones.
- For `checks.sh`: `gitleaks` 8.19 or later, on the runner and on a developer's machine (the secret scan is one of its lines). `checks.sh` scans the commits of the range; a scan of a whole worktree with dependencies installed reads `vendor`, `node_modules` and a copied env file: run it before the install, or filter to `git ls-files`.
- For `json` rows and the caps file: one of `jq`, `node` or `php`. The script takes the first it finds, in that order; `RATCHET_JSON_TOOL` forces one. With none of them a `json` row fails with "a json row needs jq, node or php".
- For `block` rows: `node` or `php`. For `neon`, `return`, `const` and `call` rows: `php`. A row whose files are all missing or whose config value is empty counts 0 and needs no tool, so a project without PHP leaves the PHP values of the config block empty.

## Environment variables and flags

| Name | Read by | Meaning |
| --- | --- | --- |
| `AUDIT_GATE_TODAY` | `audit-gate.sh` | the date to treat as today, `YYYY-MM-DD`; the proof sets it, a project never does |
| `AUDIT_EXCEPTIONS_FILE` | `audit-gate.sh` | the exceptions file; default `scripts/audit-exceptions.json` |
| `CHECK_BASE` | ratchets, commit messages, `checks.sh` | the base branch (a name on the remote). Wins over the host's own variable. `checks.sh` works it out once and exports it for the scripts it starts |
| `CHECK_HEAD` | ratchets, commit messages | the revision to check; default `HEAD`. Set it only where the host checks out exactly that revision (GitLab): never on Bitbucket or GitHub, whose pull-request pipelines check out a merge of the base into the branch |
| `BITBUCKET_PR_DESTINATION_BRANCH`, `GITHUB_BASE_REF`, `CI_MERGE_REQUEST_TARGET_BRANCH_NAME` | ratchets, commit messages, `checks.sh` | the base branch on Bitbucket, GitHub and GitLab; read when `CHECK_BASE` is empty |
| `BITBUCKET_BRANCH`, `GITHUB_HEAD_REF`, `CI_MERGE_REQUEST_SOURCE_BRANCH_NAME` | ratchets, commit messages | the head branch on Bitbucket, GitHub and GitLab; fetched, as a courtesy, when a shallow clone is deepened (a fork's branch is not on the remote and is no reason to fail) |
| `CI` | ratchets, commit messages, `checks.sh` | when set and no base branch is known, the script fails. When not set (a developer's machine) it takes `DEFAULT_BASE_BRANCH` from `kit-config.sh` |
| `RATCHET_JSON_TOOL` | ratchets | `jq`, `node` or `php` |
| `CI_CHECK_TIMEOUT` | `ci-parallel.sh` | seconds one command may run; default 600 |

| Command | Meaning |
| --- | --- |
| `ci-ratchets.sh` | compare the head with the merge base |
| `ci-ratchets.sh --head` | print `name direction value` for every row at the head; `?` for a row that cannot be counted, with the reason on standard error |
| `ci-ratchets.sh --uncovered` | list the tracked source files outside every path the rows count; exit 1 when there are any |
| `ci-ratchets.sh --self-test [<cases file>]` | run every regex row against the built-in fixture of spellings it must count and must not count, in a scratch repository; exit 1 when a row under-counts or over-counts. Needs only git (and node or php for the `block` and `neon` cases); run it from anywhere. See "What a run checks" |
| `ci-ratchets.sh --guard <path>` | run only the script checks (below); meant to be run from the base's copy of the script |
| `ci-ratchets.sh --json '<operation> <path>'` | the `json` counter over standard input |
| `debt-dashboard.sh` | the gate |
| `debt-dashboard.sh --final` | the end state: every row at zero, except the shrink-only rows; no cap above its row; `analyser-switches` at its target, and every `min` row of `MIN_ROW_TARGETS` at its number (coverage at 95 among them) |
| `--offline` | skip the rows that need a package registry, full history or today's date |
| `--details` | list the files behind every row that is not at zero |
| `--report` | always exit 0 (for reading, never in CI) |
| `checks.sh`, `--main`, `--scheduled` | the checks runs of `04-guards.md` C4; without a flag also a branch on a developer's machine |
| `checks.sh [--main] --part <name>`, `checks.sh --list-parts` | one part of the checks run (`PARTS` in its config block: `gate`, `static`, `guards`, `repo`); the names of the parts |
| `prepush.sh [--main]` | every part of `checks.sh` side by side (through `ci-parallel.sh`) |
| `checks.sh --coverage <clover file>` | a line of a test step: see "Coverage" |
| `keyed-baseline.sh <file> [--write] --format <name> -- <command>` | see "`keyed-baseline.sh`" |

## `kit-config.sh`

The values more than one script reads are set once, here. The scripts source the file from the folder they are in and stop when it is missing. It is a guarded file.

| Value | Read by | Meaning |
| --- | --- | --- |
| `DEFAULT_BASE_BRANCH`, `REMOTE` | ratchets, commit messages, `checks.sh` | the base branch on a developer's machine; the remote |
| `BASE_BRANCH_VARS`, `HEAD_BRANCH_VARS` | ratchets, commit messages, `checks.sh` | the names of the CI host's variables |
| `CAPS_FILE` | ratchets, dashboard | the caps file |
| `DATABASE_IMAGE` | no script: the contract test over the CI files | the database service image of the backend test run and the smoke run: the production engine in the production major version (`04-guards.md` C4); empty in a project without a database. It ships as a placeholder, and `checks.sh` refuses to run until it is set |

## `ci-ratchets.sh`

### Config block

| Value | Meaning |
| --- | --- |
| `SOURCE_PATHS`, `TEST_PATHS`, `FRONTEND_PATHS` | comma lists of tracked folders or files that the rows count. A trailing `?` means "may be missing"; empty means "none" |
| `TEST_SKIP_PATHS` | the test folders whose tests are written in the backend's test framework: the row `test-skip` counts there |
| `UNCOUNTED_PATHS` | tracked source files that are deliberately outside those three lists (a public entry file, root config files) |
| `SOURCE_EXTENSIONS` | the extensions that make a tracked file a source file |
| `ANALYSER_CONFIGS`, `ANALYSER_BASELINE`, `TYPE_CHECKER_CONFIGS`, `REFACTOR_CONFIG`, `AUDIT_MANIFEST`, `AUDIT_EXCEPTIONS`, `LINT_CONFIG`, `LINT_BASELINE` | the files the tool rows read: the analyser's configs and its baseline, the type checker's configs, the refactoring tool's config, the package manifest, the linter's config and its bulk-suppressions file |
| `ANALYSER_SWITCHES` | one extended regular expression that matches a strictness switch that is on, in the analyser and type-checker configs. The example value names the five PHPStan and Larastan switches and the eight TypeScript flags of the catalogs set to `true`, the three `allow...` flags set to `false`, and the two includes the catalog prescribes for the analyser: the bleeding-edge file and the date library's extension. No double quote in the value |
| `REFACTOR_SETS` | one extended regular expression that matches a set or switch that is on in `REFACTOR_CONFIG`: the 19 of the kit's config (`templates/tool-configs.md`). The row `refactor-sets` counts the matches |
| `FORMATTER_CONFIG`, `FORMATTER_RULES` | the formatter's config, and one extended regular expression that matches a rule of the strictness list that is on in it (set to `true`, or to an object of options): the 21 of the kit's config. The row `formatter-rules` counts the matches. In the three expressions: no double quote, no backslash and no dollar sign, because the merge base's value is read as text and the head's by the shell; write `[(]` and `[.]` for a literal character |
| `KEYED_BASELINES` | the folder with the files `keyed-baseline.sh` writes |
| `CI_FILES` | the pipeline files and the `scripts` folder, whole: a helper script of the project's own there is counted too |
| `OVERRIDE_AT_ZERO` | a comma list of `max` rows that take an override tag even when they stand at 0: `comments` (a comment that is needed) and `cap-dependencies-majors-behind` (a pinned major). Every other `max` row at 0 takes none. The list is in the config block, so changing it is a change of the script |
| `GUARDED_FILES` | files that may not change without the script tag (`04-guards.md` C2): `.gitattributes`, the other scripts in `scripts/`, `kit-config.sh` among them, and the pipeline files of the three hosts. A script the project adds (a secret-scanner installer, a smoke boot script, an audit helper) is guarded only when it is named here: add each one in the PR that adds it |

A row names a config value as `@NAME` in its paths column, from this block or from `kit-config.sh` (`@CAPS_FILE`). A spec that is exactly `@NAME` is that config value (the row `analyser-switches` uses it).

### The table

One row per counter: `name  max|min  method  paths  spec`. Columns are split on whitespace; `paths` is a comma list of paths, `@NAME` values and git pathspecs that start with `:` (the `comments` row uses `:(exclude)*.md`); `spec` is the rest of the line. A `max` row may only go down, a `min` row only up.

A row needs at least one real path. An exclude pathspec narrows the paths before it: a row whose paths are pathspecs alone fails with "a row needs at least one real path". Tests that live next to the source (`create-order.test.ts` beside `create-order.ts`) therefore set `TEST_PATHS` to the source folder, never to a glob.

| Method | Counts | Needs |
| --- | --- | --- |
| `grep` | matches of the extended regular expression in the tracked files under the paths (`git grep -a -o -E`: several matches on one line each count, adjacent ones that share a separator character count once; every tracked file is read as text, so a `.gitattributes` line cannot hide a file, and files with an image, font, archive, audio or video extension are left out) | git |
| `without` | two expressions: the tracked files under the paths whose name matches the first and that hold no line matching the second. It counts files that lack a line, which `grep` cannot | git |
| `number` | the whole number that the one capture group of the expression captures, first match per file | git |
| `json` | `<operation> <path>` over the JSON file. Operations: `value` (the whole number at the path), `count` (entries of the list or object), `leaves` (scalars below the path). `count?` and `leaves?` give 0 when the key is missing; without `?` a missing key is an error. The path is keys joined by dots, `.` for the root, `*` for "every child". A fourth operation, `keys`, prints the keys of the object at the path: the scripts read the caps file with it, and it is not for a row | jq, node or php |
| `block` | two expressions: the first captures blocks (it may span lines), the second is counted inside them. Keep to the syntax PCRE and JavaScript share | node or php |
| `neon` | entries under a dotted key in a NEON or simple YAML file; a `count:` under an entry adds up. A trailing `?` on the key gives 0 when it is missing | php |
| `return`, `const`, `call` | leaves of a PHP file that returns an array, of named constants, of the arguments of named method calls | php |

`return`, `const` and `call` execute code from the pull request. On a runner that builds forks or holds secrets, use the other methods only.

### The rows that ship

All are `max` rows, except the four `min` rows `analyser-switches`, `refactor-sets`, `formatter-rules` and `advisory-block`. The example values count a PHP backend with a TypeScript frontend; for another stack, change the config block and the expressions (see "Another stack").

| Row | Counts | Blind spots |
| --- | --- | --- |
| `analyser-baseline` | entries under `parameters.ignoreErrors` in the analyser configs and the baseline, each `count:` added up | a baseline in another format needs its own method |
| `analyser-ignore` | inline ignores (`@phpstan-ignore`, `@psalm-suppress`, `@noRector`, `@infection-ignore-all`, `@codeCoverageIgnore`, `phpcs:ignore`) in source and tests, an `excludePaths:` key in a config, and the config switches `reportUnmatchedIgnoredErrors`, `treatPhpDocTypesAsCertain` and `checkMissingIterableValueType` set to `false` | counts the `excludePaths:` key once, not its entries |
| `analyser-level` | the analyser configs below the top level: each config with a `level:` from 0 to 9 counts 1 (a trailing comment or quotes do not hide it), so 0 means every config is at the top | a config with no `level:` line counts 0 |
| `analyser-switches` | a `min` row: the matches of `ANALYSER_SWITCHES` in the analyser and type-checker configs, so a switch that is removed or set to false fails, and so does a removed bleeding-edge or date-extension include | a switch commented out is not counted, so the row falls; a switch set in a file the two config values do not name; a config that extends another counts only its own lines; an include that an installer plugin adds is not in the config and is not counted, which is why the catalog writes the includes out |
| `refactor-skip` | every skip entry of the refactoring tool's config: class names and strings inside `withSkip([...])` and the older `->skip([...])`, and the argument of `withSkipPath(...)` | a `])` inside a string ends the block early; a path built without a string literal |
| `refactor-sets` | a `min` row: the matches of `REFACTOR_SETS` in the refactoring tool's config, so a set that is removed or set to false fails | a set switched on through another method than the kit's config uses |
| `formatter-rules` | a `min` row: the matches of `FORMATTER_RULES` in the formatter's config, so a strictness rule that is removed or set to false fails. A rule that is still off for its own pull request is not counted, so the row rises as each lands | a rule the preset brings in without a line in the config: the kit's config names each one |
| `advisory-block` | a `min` row: 1 when the manifest requires `roave/security-advisories` at `dev-latest` | whether the requirement sits under the development requirements |
| `audit-ignore` | advisories the package manager is told to ignore (`config.audit.ignore`, Composer's key) | another package manager has another key: see "Another stack" |
| `audit-exceptions` | the entries of `scripts/audit-exceptions.json` (`AUDIT_EXCEPTIONS`). An added line raises it, so its commit carries `[ratchet: audit-exceptions] <advisory id>`; the end date, not the ratchet, makes it shrink | whether the advisory id is the one the audit prints: that is `audit-gate.sh`'s `ID_PATTERN` |
| `test-skip` | in `TEST_SKIP_PATHS` (Markdown files left out): `->skip(`, `->skipOn...(` and `->todo(` after a closing bracket (`})->skip(`, `it('x')->todo()`, `fn () => ...)->skip()`), a line that starts with `->skip(`, the function `todo(`, `markTestSkipped(`, `markTestIncomplete(`, `#[Requires...`, `@requires` (Pest and PHPUnit) | a skip built from a variable; a custom helper that skips; `collect()->skip(3)` in a test counts although it is no skip |
| `test-doubles` | `Mockery::mock/spy/namedMock/instanceMock`, `m::mock/spy`, facade doubles (`Cache::shouldReceive(`, `Log::spy()`), `$this->mock/spy/partialMock`, `test()->mock/spy/partialMock`, the functions `mock()` and `spy()` (also `\mock(`), `createMock`, `createStub`, `createPartialMock`, `createConfiguredMock`, `getMockBuilder`, `getMock...`, `createTestProxy`, `prophesize` (Pest and PHPUnit) | counts doubles of vendor classes too; a project function named `mock` or `spy` counts; anonymous classes used as fakes do not |
| `event-fake-all` | `Event::fake()` with no arguments or an empty list (`fake( )`, `fake([])`), `Event::fakeFor(` with the callback on the next line | `fakeFor` with the callback on the same line; the call split over lines after an open parenthesis that holds an argument |
| `lint-disable` | `eslint-disable`, `oxlint-disable`, `biome-ignore`, `deno-lint-ignore`, `istanbul ignore`, `v8 ignore`, `c8 ignore`, `/* eslint ... */` | another linter's marker; a lint or test command that is told to ignore findings (`--max-warnings 9999`, `--passWithNoTests`) |
| `lint-config-off` | `"off"` and `"warn"` in the lint config | severities written as 0 or 1. A core rule that is off because its typed replacement is on cannot go: it makes this row shrink-only by a decision row, like the next one |
| `lint-config-ignores` | every entry of every `ignores` list in the lint config, globs too | an entry that contains `]`; ignores built in code. An entry that cannot go (generated output, an `ignores` list that scopes a rule to one kind of file) makes this row shrink-only by a decision row (`01-principles.md`, "Shrink-only rows") |
| `lint-baseline` | the linter's bulk suppressions: every `count` in the suppressions file, added up | a suppressions file in another shape needs its own path in the spec |
| `type-baseline` | the lines of every keyed-baseline file under `KEYED_BASELINES`: one line is one finding | none: `keyed-baseline.sh` writes one line per finding |
| `ts-suppressions` | `@ts-expect-error`, `@ts-ignore`, `@ts-nocheck` | none known |
| `frontend-test-skip` | `it/test/describe` (also `test.describe`, `it.concurrent`, `.sequential`) followed by `.skip`, `.todo`, `.only`, `.skipIf`, `.runIf`, `.fails`, `.fixme`, with or without `.each` after it; `xit`, `xtest`, `xdescribe`, `fit`, `ftest`, `fdescribe` | a modifier on another line than the call |
| `comments` | `//` comments at the start of a line and after code; `#` comments at the start of a line and after `;`, `{`, `}`, `(`, `)` or `,` when a space or the line end follows; `/* */` at the start of a line, after code, after `[` or `]` and inside JSX (`{/* */}`), and a bare `/*` line; a docblock with prose on its opening line; each prose line of a docblock (a line that starts with `*`, white space and no `@`); the markup comments `<!--` and `{{--` | see below |
| `todo` | `TODO`, `FIXME`, `HACK`, `XXX` as whole words, and `@todo` | lower-case spellings; two markers that follow each other with one separator (`// TODO TODO`) count once, because the separator belongs to the first match |
| `ci-skipped-steps` | in the CI files: a command followed by a "succeed anyway" tail (two pipes and `true`, `:`, `/bin/true`, `exit 0`, `(exit 0)` or `{ :; }`; or `; exit 0`), `continue-on-error` and `allow_failure` unless set to false (`"false"` quoted is false too). `\|\| echo ...` is not counted: it is how a script prints and carries on | `set +e`; a wrapper script outside `CI_FILES`; a path condition that skips a check (Bitbucket `condition: changesets`) and a collector (`\|\| STATUS=1` that fails later or never): the contract test over the CI files holds those (`04-guards.md` C4), so in step 1 the row reads 0 on a pipeline that skips checks. Every file in `scripts/` counts, the project's own helpers too: a "succeed anyway" tail there is the same debt |
| `cap-<key>` | the cap of one dashboard row in the caps file | none: a key without its row fails the run |

What the `comments` row cannot tell, because it reads one line at a time:

- Not counted: `#[Attribute]`, `#!shebang`, `http://` and other `//` that follow a colon or a quote, `#` inside a quoted string (`'#fff'`, `href="#top"`), a `#comment` with no space after the `#`, a docblock line that holds only a tag, Markdown files.
- Counted although it is no comment: `<!--` or `{{--` inside a string; `//` or `# ` after a space inside a string or a multi-line string (`"see // here"`, a Markdown heading inside a template string), `/// <reference>` lines, a line that starts with `* ` and continues a multiplication.
- A line with two `//` runs separated by a space counts twice.

The row is shrink-only, so an over-count costs nothing and an under-count hides nothing that review does not see. Test a changed expression with `git grep -o -E -e '<expression>' HEAD -- <paths>`, which is what the script runs.

### What a run checks

1. **Each row.** The merge base is counted with the base's own definition of the row (its line and the base's config block), the head with the head's.
   The rules for a wrong-way move, the override tag, a new row and a removed row are `04-guards.md` C2. The mechanics beyond them:
   - **The tag** is a line of the commit message that starts with `[ratchet: <name>]`, one space or more and a reason; the same words in the middle of a line, in a quote or without a reason are no tag. It must be in a commit that touches a file of the row's paths. A `max` row at 0 takes none, except the rows of `OVERRIDE_AT_ZERO`.
   - **Redefined row:** when the two definitions differ, the row prints `was:` and `now:` and fails, unless a commit that touches the script carries `[ratchet: <name>] <reason>`. When only the config block or `kit-config.sh` changed, `[ratchet: script] <reason>` in a commit that touches that file is enough. The wrong-way rule still applies.
   - **Removed row:** the tag goes in the commit that removes the line from the table, for example `[ratchet: layout-list] list emptied and deleted`: the row is at 0 only on the head, and the base still counts the list. Keeping the row and deleting only the file fails with "missing <file> at the head". A rename is a removal plus a new row.
   - A required file or key that is missing at the head fails with "a list, file or key moved".
2. **The script itself and the guarded files** (`04-guards.md` C2): a file in `GUARDED_FILES` is checked when it exists on the merge base. This holds in the first run too, when the ratchet script itself is new: a pipeline file that was already there is guarded. A row cannot be named `script`.
3. **The caps file.** Every key needs a row `cap-<key>`; a key without one fails.
4. **Coverage of the tree.** Every tracked file with an extension from `SOURCE_EXTENSIONS` lies under `SOURCE_PATHS`, `TEST_PATHS`, `FRONTEND_PATHS` or `UNCOUNTED_PATHS`. A new top-level folder fails until it is in one of them.

5. **The expressions themselves.** `ci-ratchets.sh --self-test` runs every regex row against a built-in fixture (`selftest_cases` in the script): spellings the row must count and spellings it must not, one line each, with the count expected. It builds a scratch repository, so it needs no checkout and no network, and it uses the same counter as a run. It fails when a row under-counts (an expression that silently stopped matching, a new spelling that is not covered) or over-counts. `ci/checks.sh` can run it with one line, `bash scripts/ci-ratchets.sh --self-test`, next to the other script checks. A project that changes an expression changes its cases in the script in the same commit (the script tag), or keeps its own cases in a file it passes as the second argument (`<row>[@<file name>] <expected count> <text>`, one line per case; `~NL~` is a line break). The fixture also holds the expressions of "Adding a row", point 6.
6. **The expressions read what a pull request cannot move.** Every grep reads files as text, so an attribute line that marks files binary hides nothing, and `.gitattributes` is itself a guarded file. A value of the three long expressions in the config block that holds a backslash, a dollar sign or a backtick stops the run (exit 64), because the base side would read it differently from the head side.

The limit of check 2 (a pull request runs its own copy of the script) and what covers it: `04-guards.md` C2. `ci/checks.sh` runs the base's copy with `--guard` from a temporary folder, the base's script next to the base's `kit-config.sh`.

The script deepens a shallow clone until it finds the merge base, and fails with "Clone with full depth" when it cannot. On a merge-first checkout (Bitbucket's pull-request pipeline, GitHub's `pull_request` checkout of the merge ref) it compares that merge with the base, so `CHECK_HEAD` stays unset there; GitLab checks out the source branch itself and sets `CHECK_HEAD`. The head branch variable is only a courtesy fetch: a pull request from a fork has no branch of that name on the remote, and the run goes on. A branch name that starts with a dash or is not a branch name is refused for the base and ignored for the head, so a name can never be read by git as an option. A head that does not hold the script fails with "merge the base branch first". There is no skip for a pull request from the main line to a release branch: every override is in the range, so it passes on its own.

### Adding a row

1. Add the line to the table, in the pull request that adds the guard or finds the list.
2. Run `bash scripts/ci-ratchets.sh --head` and check that the number is the one you expect. For a `grep` row, run the `git grep` line above and read the matches. Add the spellings the row must and must not count to the cases of `--self-test`.
3. For a dashboard counter of the project's own: add the key to the caps file, the row `cap-<key>  max  json  @CAPS_FILE  value <key>` to the table, and a `COMMAND_ROWS` line to the dashboard. The dashboard script is a guarded file, so that commit carries the script tag. A row of the project's own also needs its line in `ROW_ADVICE`, or it prints `UNKNOWN`. Example, the CI duration: the key `ci-duration-minutes`, the row `cap-ci-duration-minutes  max  json  @CAPS_FILE  value ci-duration-minutes`, the `ROW_ADVICE` line `"ci-duration-minutes::speed up or split the slowest step"`, and the `COMMAND_ROWS` line `"ci-duration-minutes::online::<command>"` with the command of the host, which prints the minutes of the last pipeline on the main branch (`<main>`):
   - GitHub: `gh run list --branch <main> --workflow <workflow file> --status success --limit 1 --json startedAt,updatedAt --jq '.[0] | ((.updatedAt | fromdate) - (.startedAt | fromdate) + 59) / 60 | floor'`, with `GH_TOKEN` set to the job's own token and `actions: read` in the job's permissions;
   - Bitbucket (the host's own API; names as at the time of writing): `echo $(( ($(curl -s -H "Authorization: Bearer $PIPELINES_READ_TOKEN" "https://api.bitbucket.org/2.0/repositories/$BITBUCKET_REPO_FULL_NAME/pipelines/?target.branch=<main>&sort=-created_on&pagelen=1" | json 'value values.*.duration_in_seconds') + 59) / 60 ))`;
   - GitLab: `echo $(( ($(curl -s -H "PRIVATE-TOKEN: $PIPELINES_READ_TOKEN" "$CI_API_V4_URL/projects/$CI_PROJECT_ID/pipelines/latest?ref=<main>" | json 'value duration') + 59) / 60 ))`.

   `PIPELINES_READ_TOKEN` is a read-only token for the pipelines API (Bitbucket: a repository access token with the scope "pipelines: read"; GitLab: a project access token with `read_api`), stored as a secured variable of the pipeline. Making it is the owner's step (`05-program.md` D2, step 2). The parameter and field names are the hosts' at the time of writing: check them against the host's API reference when the row goes in.
4. A percentage (coverage, mutation score) is a number in a committed config file with a `min` row over it, method `number`. The rows have the same names in every project: `coverage-floor  min  number  scripts/coverage-floor.txt  ^threshold: ([0-9]+)$` (see "Coverage"); where the project has a frontend, `frontend-coverage-floor  min  number  <the frontend test runner's config>  <expression with one capture group>` over the threshold of the whole frontend and `frontend-logic-coverage-floor`, the same form over the threshold of the logic folders; and `mutation-floor  min  number  scripts/mutation-floor.txt  ^threshold: ([0-9]+)$` (see "Mutation").
5. A baseline is counted per entry, not per file: `neon` for a NEON baseline, `json` with `count` or `leaves` for a JSON one, `grep` with `^.` for one entry per line.
6. Files that lack a line are a `without` row. The one every PHP project adds in step 2 where it has such files: `strict-types-missing  max  without  @SOURCE_PATHS,@TEST_PATHS  (^|/)[^/.]+\.php$ ^declare\(strict_types=1\);` (the first expression leaves out templates such as `page.blade.php`). The row stays when it reaches 0. Three more rows of this kind count the sites of a formatter rule that is still off (`templates/tool-configs.md`, "Rules that can change behavior"), with the same names in every PHP project, and stay at 0 afterwards:
   - `loose-comparisons  max  grep  @SOURCE_PATHS,@TEST_PATHS  (^|[^=!<>])(==|!=|<>)([^=]|$)`
   - `mutable-dates  max  grep  @SOURCE_PATHS,@TEST_PATHS  (^|[^A-Za-z0-9_])(DateTime|Carbon)(::|[[:space:]]*[(;,)])` (also `new DateTime;`)
   - `byte-string-functions  max  grep  @SOURCE_PATHS,@TEST_PATHS` and the expression `(^|[^A-Za-z0-9_>:$\\])\\?(strlen|substr|strpos|strrpos|stripos|strripos|strstr|stristr|strrchr|substr_count|str_split|strtolower|strtoupper)[(]`: the thirteen functions the formatter rule rewrites, also written fully qualified (`\strlen(`), not as a method or a static call. `ci-ratchets.sh --self-test` holds the cases of these four expressions and of `strict-types-missing` and `guide-future-tense`: add the row, then run it.

   One row of the same kind goes in later: `model-strictness-violations  max  grep  <the counted list of step 4>  ^.` with the recording PR of step 4.
7. The guides get one row in step 3, with the same name in every project: `guide-future-tense  max  grep  <every AGENTS.md and the review rubric, as a comma list>  (^|[^A-Za-z])([Ww]ill|[Ff]or now|[Uu]ntil)([^A-Za-z]|$)`. A guide describes the final state, so the row goes to 0.
8. The ignore list of the unused-code tool has the same name in every project: `unused-ignore  max  json  <the tool's config>  count? <key of the list>`, one row per list key when the tool has several.
9. Two package managers in one project (a PHP backend with a TypeScript frontend) keep the three dependency keys and the shown row `dependencies-behind`: each `COMMAND_ROWS` command adds up both managers. A command inside double quotes is expanded when the array is defined, so a command substitution is written `\$( ... )` there, or the whole entry is in single quotes (and then holds no single quote). Ready lines for the config block:
   - advisories: `audit-gate.sh` reads one manager's JSON (its `ID_PATTERN` is that manager's key). The second manager is a count added in the same row, for example `'dependency-advisories::online::echo $(( $(composer audit --locked --format=json --no-interaction | bash scripts/audit-gate.sh) + $(npm audit --omit=dev --json | json "value metadata.vulnerabilities.total") ))'`, until the project gives the gate a pattern per manager;
   - majors behind, npm: `npm outdated` prints one entry per package with `current` and `latest`, and the `json` helper cannot compare two fields, so use a one-line node count in single quotes: `'dependencies-majors-behind::online::echo $(( $(composer outdated --locked --direct --major-only --format=json --no-interaction | json "count locked") + $(npm outdated --json | node -e "let s=\"\";process.stdin.on(\"data\",d=>s+=d).on(\"end\",()=>{const o=JSON.parse(s||\"{}\");console.log(Object.values(o).filter(v=>v.current&&v.latest&&v.current.split(\".\")[0]!==v.latest.split(\".\")[0]).length)})") ))'`;
   - `npm outdated` and `npm audit` exit 1 when they find something: the dashboard runs the command without `pipefail` and reads the printed number, never the status, so no `|| true` is needed (and one in a script counts in `ci-skipped-steps`).
   A project without PHP replaces the commands with its package manager's. The manifest check that works with peer-dependency flags is `npm ci --dry-run --ignore-scripts`; `npm ls --all` fails on a project that needs `legacy-peer-deps`. Composer: add `--abandoned=report` to `composer audit` in the checks line, or an abandoned package fails the audit (the dashboard caps abandoned packages in their own row).

10. `autoload-conformance` is a dashboard counter with a command, not a pattern in the files, so it is a `COMMAND_ROWS` row with a cap, like the CI duration of point 3: the key `autoload-conformance` in the caps file, the row `cap-autoload-conformance  max  json  @CAPS_FILE  value autoload-conformance`, a `ROW_ADVICE` line (`"autoload-conformance::move or rename the class to the name its path says, or the file to its class"`) and a `COMMAND_ROWS` line `"autoload-conformance::always::<command>"`. The command runs `composer dump-autoload -o --no-interaction` and prints how many of its lines say that a class does not comply with the PSR-4 standard (verify the exact wording of that message against the installed Composer in step 2, and count it with `grep -c`); a class that the autoloader skips is only logged, never failed, so the number is the only place it shows. It starts at the measured value in step 2 and goes to 0.

### Coverage

The test step measures the coverage and holds it at the committed floor:

- PHP, in Pest and in PHPUnit: the test runner's own coverage with `pcov` as the driver writes a clover report (`--coverage-clover <file>`), and the next line of the test step is `bash scripts/checks.sh --coverage <file>`. It fails when the statements of the report's project metrics are below the line `threshold: <number>` of `scripts/coverage-floor.txt` (row `coverage-floor`), and prints the number to raise the floor to. The two lines are the composer script `test:unit`. The floor file is the one number: the test runner gets no minimum of its own (`--min`, `--exactly`), which would be a second place to change.
- TypeScript: vitest's own `coverage.thresholds` in its committed config; the row is `frontend-coverage-floor`, or `coverage-floor` in a project whose only language is TypeScript. A frontend has two numbers there: the threshold of the whole frontend, and a threshold keyed by the glob of the logic folders (hooks, utils, lib), which the row `frontend-logic-coverage-floor` holds. The glob-keyed form (`thresholds["<glob>"]`) was seen to work on vitest 5 in a real run; verify in step 1 against the installed version, and on any other runner. The row `frontend-logic-coverage-floor` is not in the start set: the project adds it with the committed threshold (`MIN_ROW_TARGETS` names it, and `--final` fails a name without a row).
- The end goal: `--final` fails `coverage-floor` and `frontend-logic-coverage-floor` below 95 (`MIN_ROW_TARGETS`). The floor reaches 95 by being raised to the measured value at the end of each step, never by writing 95 in the file ahead of the tests.

The `--coverage` line is proven against a clover file written by hand, not against a real coverage run.

### Mutation

The floor is one file in every project: `scripts/mutation-floor.txt`, one line `threshold: <number>`, held by the row `mutation-floor` ("Adding a row", point 4). Below, `<floor>` is `$(sed -n 's/^threshold: //p' scripts/mutation-floor.txt)` and `<changed>` is the comma list of `git -c core.quotePath=false diff --no-renames --name-only --diff-filter=AM "origin/$CHECK_BASE"...HEAD -- <source folders>` (`--no-renames`: a renamed and edited file is a changed file; `core.quotePath=false`: a non-ASCII path is passed as it is); a pull request that changes no source file skips the run. Per tool: the whole-tree run of the scheduled job, then the run on a pull request, from step 9 on. The pipeline calls `bash scripts/mutation.sh` (whole tree) and `bash scripts/mutation.sh --changed`, never an inline command; the script holds the commands below and reads the floor file.

- **Pest**: `vendor/bin/pest --mutate --everything --parallel --min=<floor>` and `vendor/bin/pest --mutate --parallel --path=<changed> --min=<floor>`. Without `--everything` or `--path`, Pest mutates only what a test declares with `covers()` or `mutates()`. A run on a sample, as run in one project on Pest 5: `vendor/bin/pest --mutate --parallel --processes=6 --passthru-php="'-d' 'memory_limit=-1'" --path=<the ten files>`. It needs a coverage driver (`pcov`); without the memory passthrough the workers die at 128 MB with "Premature end of PHP process", which reads like a crash of the tool. The summary lines are `Mutations: <n> uncovered, <m> tested` and `Score: <x>%` (ten classes: 335 s wall, 32 s of it mutating). Two of the options were read in the plugin's source, not run: `--everything` and `--min`.
- **Infection** (a PHPUnit project): `vendor/bin/infection --threads=max --min-msi=<floor>` and the same with `--git-diff-filter=AMR --git-diff-base=origin/$CHECK_BASE` (`R`: a renamed file is mutated). Not run for this kit: verify the options against the tool's documentation in step 2.
- **Stryker** (TypeScript): `npx stryker run`, and the same with `--mutate <changed>` (the comma list as one argument). Stryker has no command-line option for the break threshold: `mutation.sh` sets the environment variable `MUTATION_FLOOR` from the floor file, and `stryker.config.mjs` reads it (`thresholds: { break: Number(process.env.MUTATION_FLOOR) }`), so the floor file stays the one place of the number. A config that does not read it holds no floor: check that in step 2. Not run for this kit: verify the key and the option against the tool's documentation.

A whole-tree run that does not fit the maximum time of one job is split by module over parallel jobs of the scheduled run; the floor is the lowest score of the parts.

### Another stack

Set the config block, then go through the table once:

- Empty the values the stack lacks (`REFACTOR_CONFIG=""`, `FORMATTER_CONFIG=""`); the row then counts 0. Keep the row. For a `min` row with an end goal that means: take its entry out of `MIN_ROW_TARGETS`, or put the stack's own names in `REFACTOR_SETS` and `FORMATTER_RULES`, point `FORMATTER_CONFIG` at the stack's formatter or linter config, and set the entry to the number of names. `advisory-block` has a subject only where the package manager can refuse an install (`catalog/other-stacks.md`, "Advisory-blocking dependency").
- Replace the expressions that name a tool: `analyser-ignore` (the checker's inline ignore), `test-skip` and `test-doubles` (the test framework's and the mocking library's spellings, every one of them), `lint-disable`, and the key of `audit-ignore` (pnpm: `leaves? pnpm.auditConfig`; npm has no ignore list of its own, so the row reads 0 there, and a wrapper that adds one is debt: remove it).
- No two rows count the same line. A project whose only language is TypeScript keeps the expression of `analyser-ignore`, which reads 0 there: `ts-suppressions` counts the checker's inline ignores. Its `test-doubles` expression, for vitest, is `(^|[^A-Za-z0-9_$.])vi\.(mock|doMock|spyOn|fn|mocked)\(`.
- `test-skip` and `frontend-test-skip` never count the same line. A project whose only language is TypeScript sets `TEST_SKIP_PATHS=""`: `frontend-test-skip` counts its skips and `test-skip` reads 0. A backend in another language keeps `TEST_SKIP_PATHS` and replaces the expression of `test-skip`.
- `UNCOUNTED_PATHS`: the lint config, because its own rows read it. Every other root file with a source extension (`vitest.config.ts`) goes into `SOURCE_PATHS`, with a `?`: the run fails on a source file that is in none of the lists.
- `analyser-level` counts configs with a numeric level below the top. A checker without a numeric level (the TypeScript compiler) leaves `ANALYSER_CONFIGS` empty and is held by `analyser-switches` alone: a `grep` row counts matches, so it cannot count a switch that is missing, and a `min` row over the switches that are on can.
- `ANALYSER_SWITCHES`: the names of the stack's strictness switches, in the form the config file writes them.
- `comments`: keep the alternatives for the comment markers the languages have, add the marker of the template language, and drop the others.
- Replace the cases of `--self-test` that belong to a replaced expression (or pass a file of the project's own cases): it fails on an expression that no longer counts the spellings of the stack it was written for.
- Keep every row name: the dashboard, the plan and the guides use them.

A TypeScript backend (kebab-case files, tests next to the files) sets, in the ratchet script: `SOURCE_PATHS="src,vitest.config.ts?"`, `TEST_PATHS="src"`, `TEST_SKIP_PATHS=""`, `FRONTEND_PATHS=""`, `UNCOUNTED_PATHS="eslint.config.js?"`, `ANALYSER_CONFIGS=""`, `ANALYSER_BASELINE=""`, `TYPE_CHECKER_CONFIGS="tsconfig.json"`, `REFACTOR_CONFIG=""`, `AUDIT_MANIFEST="package.json"`. In the dashboard: `SOURCE_FILES='^src/.*\.ts$'`, `FRONTEND_FILES=''`, `TEST_FILES='\.test\.ts$'`, `OPEN_CLASS=''` (the language cannot close a class; `catalog/other-stacks.md`, "Final by default"), `BEHAVIOR_CLASSES='^src/modules/[^/]+/(actions|services|jobs|listeners|commands)/(.*/)?[^/]+\.ts$'` plus today's folders as alternatives until step 5 has moved them (`02-step-1-discover.md` 1.6), `SUPPORT_CLASSES` the same over the support folders the project has, `NOT_A_CLASS=''`, `TEST_NAME_SUFFIX=".test"`, `FRONTEND_LOGIC=''`, `FRONTEND_COMPONENTS=''`, `ANALYSER_SWITCHES_TARGET=11` (the flags of the "Strictness" table, in one `tsconfig.json`), `MIN_ROW_TARGETS="coverage-floor:95"`, and its package manager's commands in `COMMAND_ROWS`. The rows `lint-disable`, `ts-suppressions` and `frontend-test-skip` read the source and test paths as well as the frontend paths, so they count in a TypeScript backend without a change.

A PHP project with server-rendered templates and no frontend source folder sets, in the dashboard, `SOURCE_FILES='^(app/.*\.php|resources/views/.*\.blade\.php)$'`, so the view folder is in the size rows, and `FRONTEND_FILES=''`, `FRONTEND_LOGIC=''`, `FRONTEND_COMPONENTS=''` where it ships no script file of its own.

## `debt-dashboard.sh`

Every counter in one table: the ratchet rows (from `ci-ratchets.sh --head`), the row `files-outside-row-paths`, and the rows it measures itself. It counts tracked files only.

| Row (and caps key) | Counts |
| --- | --- |
| `source-files-over-limit`, `frontend-files-over-limit`, `test-files-over-limit` | files over `MAX_LINES` |
| `open-classes` | source files with a line that matches `OPEN_CLASS`: a concrete class declared without the closing keyword (indented, or after an attribute on the same line, too). An abstract class is not counted; a concrete class that another class extends is, like any other open class. It is the counter of the formatter rule that closes classes (`05-program.md` D8) |
| `classes-no-test-names` | behavior classes that no test file imports or names before `::`. An import is a line with `use`, `import`, `from`, `export ... from` or `require(`; the name is the last part of the imported path, with and without its extension, so `use App\Actions\CreateOrder;` and `import { createOrder } from "./create-order";` name `CreateOrder.php` and `create-order.ts` |
| `classes-without-own-test`, `support-classes-without-own-test` | classes without a test file named `<Class><TEST_NAME_SUFFIX>`: `CreateOrderTest.php` for `Test`, `create-order.test.ts` for `.test`. A file that matches `TEST_FILES` is never counted as a class |
| `frontend-logic-without-test`, `frontend-components-without-test` | files without a `<file>.test.*` next to them |
| `docs-older-than-limit` | docs whose last change is older than `DOCS_MAX_AGE_DAYS` |
| `dependency-advisories`, `dependencies-abandoned`, `dependencies-majors-behind` | the `COMMAND_ROWS` of the config block: each is a command that prints one whole number |
| `dependencies-behind` | the same, as the one `shown` row: packages a minor or patch version behind. It has no cap and never fails; the updates are the dependency bot's work |

The frontend test rows count every file that matches `FRONTEND_LOGIC` and `FRONTEND_COMPONENTS`, so pages, entry points (`app`, `ssr`), vendored UI primitives and test helpers count too unless `FRONTEND_NOT_COUNTED` names them by category; a project decides in step 1 whether primitives are covered by stories or by tests, and keeps helpers in a folder the expression excludes (`test/mocks/`).

Blind spots: two classes with the same name in different folders count as one for the test rows; a test file found by name may sit in any test folder; a class is matched by its file name; an import of `./order.service` also names a file called `order`; an attribute with a `]` inside its arguments in front of `class` hides the class from `open-classes`, and a line of a multi-line string that starts with `class` counts as one.

### Modes

The rules of the gate, of `--final` and of the caps are `04-guards.md` C3; this is what the script prints.

- **Default, the gate.** A measured row above its cap is `OPEN`. A ratchet row has the status `held`. A row below its cap passes and says "lower the cap to N". The row `files-outside-row-paths` has no cap: it must be 0 in both modes.
- **Every `OPEN` row is followed by one line, "do this: ..."**: what brings the row to its target. The lines of the rows that ship are in the script; a row the project adds takes its line from `ROW_ADVICE`.
- **`--final`.** Every row must be at zero: ratchet `max` rows, caps and measured rows. A cap that is above its row fails with "the cap N is above the row". The rows in `SHRINK_ONLY` are shown as `shrink-only`: a ratchet row by its name, a cap as `cap-<key>` (its measured row is then held to the cap). Which rows may be in it: `01-principles.md`, "Shrink-only rows". A `min` row is `held`, except the ones with an end goal: `analyser-switches` fails below `ANALYSER_SWITCHES_TARGET`, and a row named in `MIN_ROW_TARGETS` fails below its number there; each prints `UNKNOWN` when the goal is no number, and a name in `MIN_ROW_TARGETS` without a ratchet row prints `UNKNOWN (no ratchet row ...)`. In the gate such a row is `held` and shows its goal in the target column.
- **A `shown` row** (`dependencies-behind`, and no other) is printed with its value in both modes, skipped by `--offline`, and never fails.

`UNKNOWN` is printed, and fails both modes, for: a row that cannot be counted (`ci-ratchets.sh --head` prints why); a measured row whose key is missing from the caps file; a caps key without its `cap-<key>` ratchet row; a caps key that no dashboard row measures; a caps file that cannot be read; a `COMMAND_ROWS` key of the project's own without a line in `ROW_ADVICE`.

### Config block

| Value | Meaning |
| --- | --- |
| `RATCHET_SCRIPT` | the ratchet script (empty: `ci-ratchets.sh` next to the dashboard). The caps file is `CAPS_FILE` in `kit-config.sh` |
| `MAX_LINES` | the kit's limit for a file, 400 |
| `SOURCE_FILES`, `FRONTEND_FILES`, `TEST_FILES` | extended regular expressions over tracked paths: what a source file, a frontend file and a test file are. Empty means "no such files". The default scope of `SOURCE_FILES` covers the same folders as `SOURCE_PATHS` of the ratchet script (`app`, `bootstrap`, `config`); a long config file is a source file. The method-length guard of step 3 reads this one scope |
| `SIZE_EXEMPT` | paths the size rows skip: generated files |
| `OPEN_CLASS` | a line that declares a concrete class that is open for extension: no closing keyword, and not abstract. Empty `OPEN_CLASS`: the row counts 0. It is empty only for a language that closes classes by default, or that cannot close a class (TypeScript, JavaScript: a lint rule holds the pattern, `catalog/other-stacks.md`, "Final by default") |
| `BEHAVIOR_CLASSES`, `SUPPORT_CLASSES` | the paths of the classes that need a test; a file in a subdomain folder under one of the folders counts too. Until the moves of step 5, each also names today's folders (`02-step-1-discover.md` 1.6); so do the two `FRONTEND_` expressions below until step 8 |
| `NOT_A_CLASS` | a line that makes the test rows skip a file (an abstract class, an interface, a trait) |
| `TEST_NAME_SUFFIX` | what a test file adds to the name of the file it covers (`Test`, or `.test` for `create-order.test.ts`) |
| `FRONTEND_LOGIC`, `FRONTEND_COMPONENTS`, `FRONTEND_NOT_COUNTED`, `FRONTEND_TEST_INFIX` | the frontend files that need a test next to them, the files that never do, and the infix of a test file (`test`) |
| `DOCS_PATHS`, `DOCS_MAX_AGE_DAYS` | git pathspecs, split on spaces: the docs folder, with the decision records and the archive folder excluded as history; the age limit in days. Empty `DOCS_PATHS`: the row counts 0 |
| `SHRINK_ONLY` | the ratchet rows `--final` does not fail (see "Modes") |
| `ANALYSER_SWITCHES_TARGET` | what `analyser-switches` must reach: the switches the catalog names, added up over the configs that hold them (the example: 7 in `phpstan.neon`, the five switches and the two includes, and 11 in `tsconfig.json`, 18) |
| `MIN_ROW_TARGETS` | the end goals of the other `min` rows, as `<row>:<number>` split on spaces: `coverage-floor:95 frontend-logic-coverage-floor:95 refactor-sets:19 formatter-rules:21 advisory-block:1`. The numbers are the kit's. A project removes the entry of a row whose subject it lacks, or lowers the number where the subject exists only in part: the organisation hosts the formatter config and it lacks rules, so `formatter-rules` is held at the number the hosted config has, with the decision row of every missing rule in the plan. A number below the kit's prints `ok (goal lowered: the kit holds 21; needs a decision row in the plan)` in every run |
| `COMMAND_ROWS` | more rows, each a command |
| `ROW_ADVICE` | `"<caps key>::<what to do>"`: the "do this" line of each `COMMAND_ROWS` key the project adds |

A `COMMAND_ROWS` line is `"<caps key>::<always|online|shown>::<command>"`: `online` is skipped by `--offline`, and `shown` is the form of `dependencies-behind` (see "Modes"). The command prints one whole number on one line; anything else (text, two lines) is `UNKNOWN`. `json '<operation> <path>'` is available in it. This is where a project puts its package manager, and any counter of its own (CI minutes, a count from a tool's report). The dashboard script holds no number and no file name outside its config block.

Every expression of the block is compiled once at the start: an invalid one stops the run with exit 64, so a typo cannot make a row read 0. A skipped row names its reason (`skipped (--offline)`, `skipped (shallow clone)`). `docs-older-than-limit` dates a doc by its last commit, so in a shallow clone the gate skips it and `--final` prints `UNKNOWN (shallow clone ...)`: the scheduled run fetches the full history.

The dashboard reads the ratchet rows at the last commit and its own rows from the working tree, so run it on a clean tree.

## `debt-caps.json`

One object, key to whole number. Every key is a dashboard row and has a `cap-<key>` ratchet row. The rules of a cap (it only goes down, the tag that raises it, the lead lowers it to its row at the end of every step) are in `04-guards.md` C3.

## `audit-gate.sh`

`composer audit --locked --format=json --no-interaction | bash scripts/audit-gate.sh` (the audit of each package manager, as JSON). It prints one whole number, the advisories that no active exception covers plus the broken entries of the exceptions file, and exits 1 above 0: the same line is the `Audit` task of `checks.sh` and the command of the dashboard row `dependency-advisories`.

The exceptions file is `scripts/audit-exceptions.json`, one entry per line so a reviewer reads one line: `{"id": "<advisory id>", "reason": "<why the code path is not reachable, or what mitigates it>", "added": "YYYY-MM-DD", "expires": "YYYY-MM-DD"}`. An entry fails the gate when a field is missing, a date is not a date, `added` is in the future, `expires` is more than `MAX_DAYS` (7) after `added`, `expires` is in the past, or the id is listed twice. An exception is never renewed: it is replaced by the fix, and the line is deleted with it. Input that is not one complete JSON document with the advisories key (empty, truncated, an error object) fails too, so a crashed audit is not a green one. An advisory id that the audit lists twice counts once; an `id` with a space, a reason of spaces and two entries on one line are broken entries. Dates are UTC (`added` and `expires` are UTC days, as in the dashboard header).

What it cannot see: that `added` is the real day (a reviewer reads the commit date), and that the reason is true. `EXCEPTIONS_FILE` and `MAX_DAYS` are in its config block. `DOCUMENT_PATTERN` is the key a complete audit holds (Composer: `"advisories"`); `ID_PATTERN` is Composer's `advisoryId`; for another package manager it is the key of the audit's JSON that holds one advisory id (verify in step 1 that it appears once per advisory). `AUDIT_GATE_TODAY` sets the date for the proof.

## `ci-commit-messages.sh`

One form: `<TICKET> <type>[(scope)][!]: <subject>`, for example `PROJ-123 feat: add the export`. The config block holds `TICKET`, an expression such as `PROJ-[0-9]+` (a project without a ticket system sets its fixed prefix, `TICKET='NOTICKET'`); `TYPES`, the accepted types (`feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `style`, `ci`, `perf`, `build`, `revert`); and `EXAMPLE`, the example the failure message prints. `TICKET` and `EXAMPLE` ship as placeholders, and the script refuses to run with one. `GENERATED`, also in the config block, is the pattern of the titles that pass without the form; a project that keeps a tool-made title adds its exact text there, with the script tag. The base branch and the remote come from `kit-config.sh`. `type(TICKET): ...` is not accepted.

These pass without the form: a commit with more than one parent, and the subjects that match `GENERATED` over the whole subject (`Merge <sha> into <sha>`, `Merged in ... (pull request #N)`, `Revert "..."`, `Reapply "..."`; the quote must end the subject). `Merge branch ...` and `Merge pull request ...` are no longer listed: they are merge commits, which have two parents and are skipped, and on a commit with one parent they would hide work.

It reads the commits between the base and the head. With no base branch in CI it fails. It deepens a shallow clone and fails when it cannot. What landing it does to open branches and bots: `07-process.md` section 10.

## `ci-parallel.sh`

`bash scripts/ci-parallel.sh "Name::command" "Name::command"`. Each command runs in its own process group with its output in a file; the output is printed under the name when the command ends; a summary of `PASS`, `FAIL` and `TIMEOUT` closes the run. Any failure fails the run. A command over the limit is stopped with its children and reported as `TIMEOUT`. The config block holds `DEFAULT_TIMEOUT` (600 seconds; `CI_CHECK_TIMEOUT` overrides it for one run) and `GRACE`, the seconds between stop and kill; both are whole numbers of seconds, or the run fails with 64. A task without `::`, with no name or with a blank command fails with 64 before anything runs (a command that expanded to nothing is not a green check). Stopped with INT or TERM, it stops every command and every watchdog. It needs no `timeout` and no `wait -n`, so it runs on macOS bash 3.2; the copy in `reference-project/scripts/` is this file.

## `keyed-baseline.sh`

`bash scripts/keyed-baseline.sh <baseline file> --format <name> -- <command>`, as a line of the parallel call in `checks.sh`, one line per check: one per type-checker flag that is not at zero yet, and one for the unused-code tool. Examples: `"Flag noUncheckedIndexedAccess::bash scripts/keyed-baseline.sh baselines/no-unchecked-indexed-access.txt --format tsc -- npx tsc --noEmit --noUncheckedIndexedAccess"` and `"Unused code::bash scripts/keyed-baseline.sh baselines/unused-code.txt --format knip -- npx knip --no-progress"`.

- `--format` names the tool whose output the command prints; it is required. The config block holds, per name in `FORMATS`, three values and an optional fourth: `FINDING_<name>` (the output lines that are findings), `NORMALIZE_<name>` (a `sed -E` expression that removes line and column numbers), `SECTION_<name>` (empty, or the expression of a heading line: a finding is then written as "heading: finding", so one line under two headings is two findings; with a section expression, a line before the first heading is no finding) and `SKIP_<name>` (optional: a heading whose lines are not findings). Two formats ship: `tsc`, the TypeScript compiler (a finding has a position, `file(1,2): error TS1234` or `file:1:2 - error TS1234`; a start-up error without one is no finding, so the run fails), and `knip`, the unused-code tool in its default reporter (a heading per kind, one line per finding). A tool with another output gets its own values and its name in `FORMATS`.
- It runs the command, keeps the findings, normalizes and sorts them, and compares them with the file. A finding that occurs twice is two lines.
- It fails on a finding that is not in the file ("fix them; a line is never added") and on a line that is no longer found ("drop them by running this command with --write"). It fails when the command fails without a finding, so a crashed tool does not read as zero findings.
- `--init` writes the file from the findings: step 2 writes each file once with it. `--write` rewrites an existing file and only drops fixed lines: it refuses (exit 1, the file untouched) when the command prints a finding the file lacks. The ratchet row `type-baseline` counts the lines of every file under `KEYED_BASELINES` and fails a file that grows.
- At zero the file is deleted with its line in `checks.sh`, and a flag goes into the config, where `analyser-switches` holds it.
- The `knip` format drops the tool's "Configuration hints" section (`SKIP_knip`): those lines are not findings, so fix the hints in the tool's config first. It is proven against recorded output in the shape of the tool's default reporter, not against the tool itself: read the first baseline file it writes.

## `move-ts.mjs` and `map-normalise.sh`

`node tools/codemods/move-ts.mjs <moves.tsv> [--reverse] [--verify] [--alias @=src] [--roots src,tests]`: for each map line `git mv` of a file still at its old path, then a rewrite of the specifiers in the tracked files under the roots. A file that moved gets its relative specifiers recomputed from its new folder; an importer of a moved file gets the new specifier; an alias specifier follows. The map may have CRLF line ends. Before the first `git mv` every line is checked: a `from` that is not tracked, a `to` that already exists next to its `from`, or two lines with one `to` stop the run with exit 1 and nothing moved. The rewrite covers `from`, `import()`, `import` for side effects, `require()`, `require.resolve()` and the calls `mock`, `doMock`, `unmock`, `doUnmock`, `importActual`, `importMock`, `requireActual`, `requireMock` and `setMock`; paths are read with `-z` and unquoted, so non-ASCII names work. A second run changes nothing; `--verify` exits 1 when an old path exists, a new path is missing, a specifier names an old path, or a moved file has a relative or alias specifier that names no file (a half move). `--reverse` swaps the columns; a forward and a reverse run are valid but not byte-identical (`"../a"` becomes `"./"`, an `/index` is dropped). A path built from a string at run time is the text pass of the move (`05-program.md` D9). `bash scripts/map-normalise.sh <moves.tsv> [--forward] < dump > normalised`: see its header.

## `ci/`

- `checks.sh` is the one command; what it runs and in which order is in `04-guards.md` C4. Its config block holds `DASHBOARD_MODE` (empty, and `--final` from the exit of step 10 on) and `COVERAGE_FLOOR_FILE`; the base branch and the remote come from `kit-config.sh`. Every `<placeholder>` is the project's command; the script refuses to run while one is left outside a comment line in it, in `kit-config.sh` or in the config block of the commit-message check, and names the file and the line. The guard tests line is the test runner's command for the guard group and nothing else (PHP: `--group arch` in Pest and in PHPUnit; vitest: the guard project or folder). The secret scan line is `gitleaks` over every commit between the base and the head. The `Audit` line has two forms: the full audit, and in `--main` (a deploying branch) the production dependencies only (`04-guards.md` C4, point 21). The project adds its other whole-tree checks to the `TASKS` list. In a PHP project the lines are the commands of the composer scripts (`catalog/tests-database-ci-docs.md`, "Composer scripts"): `Format` is the formatter with `--test` (and the frontend formatter's check as a line of its own), `Refactor` is the refactoring tool with `--dry-run`, `Analyser` is the analyser (and the type checker as a line of its own), `Type coverage` is the command of `test:type-coverage`, and the project adds `Manifest floor::composer bump --dry-run`, which exits 1 while a requirement is behind. A stack without a refactoring tool or a type-coverage tool deletes that line; the contract test then holds that the stack's tooling table names none. It calls the tools as `scripts/ci-ratchets.sh`, `scripts/debt-dashboard.sh`, `scripts/ci-parallel.sh` and `scripts/ci-commit-messages.sh`.
- `PARTS` in the config block groups the tasks, one entry `<part>::<task names split by a vertical bar>`; the script stops when a task is in no part or in two, so a split pipeline cannot drop a check, and `--part gate` runs the guard of the ratchet script, the ratchets and the dashboard. The `Commit messages` and `Secret scan` tasks exist in a pull-request run only.
- It stops, and says so, when the base cannot be fetched, and when a CI run has no base branch and is not `--main`.
- The three pipeline files hold the five runs of `04-guards.md` C4. A repository that already has a Bitbucket or GitLab pipeline takes their steps and jobs into its own file; on GitHub the skeleton is a file of its own, `.github/workflows/kit-checks.yml`, and the existing workflow is not edited (`05-program.md` D2, step 2; `04-guards.md` C4).
- `prepush.sh` fetches the base once and then runs every part side by side; it takes `--main` or nothing. `checks.sh` takes its flags in any order (`--main`, `--scheduled`, `--coverage <file>`, `--part <name>`, `--list-parts`) and rejects a combination it does not list: `--part` goes with a pull-request run or `--main` only, the rest stand alone. A part fetches the base only when it reads it.
- `CODEOWNERS.example` covers the files that carry the guards with a code-owners rule (`04-guards.md` C4, point 22; `07-process.md` section 12); copy it to the host's path and replace `<owner>` with people or teams.
- The test step of a pull request in the skeletons sets `CHECK_BASE` from the host's variable, for `mutation.sh --changed`.

## Proof

`tools/proof/proof.sh` builds scratch repositories in a temporary folder and runs its scenarios against the scripts in `tools/` and `ci/`. Run it when the kit's scripts changed since its last run on this machine: `bash tools/proof/proof.sh /bin/bash` and, where `command -v bash` is another binary, `bash tools/proof/proof.sh "$(command -v bash)" other` (the second argument names the log). One run takes seven minutes or more. It prints one line per scenario (scenario, expected, got, ok or WRONG) and exits non-zero when one is wrong: that table is the list of what is proven. It needs `jq` and `node`. Without `php` on the PATH the scenarios that count a NEON file are printed as "skipped: needs php" and the run still ends with 0 wrong; the TypeScript scenarios run everywhere. The strictness rows are proven against the configs of `templates/tool-configs.md` themselves: the proof cuts the formatter and refactoring configs out of that file and counts them. What the proof does not do: it runs no real analyser, formatter, refactoring tool, coverage tool, unused-code tool or CI host, and it replaces the secret-scan line by `true`. `PROOF_OUT` names the folder for the scratch repositories, the full log and the table (default: a new temporary folder); `KIT` names the kit folder to prove (default: the one the file is in).
