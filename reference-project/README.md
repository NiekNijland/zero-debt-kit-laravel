# Read before copying: where this reference falls short of the kit

This folder is another project's end state: a multi-tenant Laravel and React application on GitHub with Postgres and Pest. It is an anonymized extract: the domain is rewritten to an example web shop platform, the rows of its guides are cut to the ones that illustrate the kit's patterns, the lists (stored class names, aliases, route tables) are shortened, and the application code the guards run against is not included, so nothing here runs as it stands. Copy the idea and the shape of a guard, a config or a guide from it. Never copy these: where the reference and the kit differ, the kit wins.

- **A bot that commits.** Its pipeline checks the formatting of the whole tree, but still carries a bot that formats the changed files and commits (the `Codestyle` commands and `CI_AUTOFIX` in `scripts/`), and runs the refactoring tool on changed files only. Copy the `Formatting` commands, not the rest.
- **Scripts that name the project.** The scripts in `scripts/` name its own folders, classes and ticket prefix, hold their caps under one ratchet row over the sum, and carry tool names in their rows. The neutral versions are the kit's `tools/`: copy those.
- **Two detectors with literal paths.** `tests/Support/Architecture/LayoutGuard.php` (the layout folders) and `tests/Support/Architecture/TestTierGuard.php` (the pure-layer folders and the bootstrap file of the test framework) hold paths of the project. The kit's guard over the guard folder fails both: read the folders from the layout table in the guide (`catalog/tests-database-ci-docs.md`, "Guard shape").
- **Two spellings of a test.** Its tests declare a test as `it(...)` and as `test(...)`, with a rule for when each fits (`tests/AGENTS.md`, "Write a test"). The kit has one spelling, `it(...)` (`catalog/tests-database-ci-docs.md`, "One spelling in a test").
- **A placeholder for the coverage upload.** `scripts/ci-php-tests.sh` and `scripts/ci-frontend-tests.sh` call `scripts/upload-coverage.sh`, which stands for the uploader of the coverage service and is not in this extract; `coverage-service.yml` shows the shape of that service's config under a neutral file name.
- **Guards it does not have.** No guard for classes closed by default, no coverage test for object-level access, no secret scan, no smoke suite, no contract test over its own CI files (the kit's example is `tests/Unit/CiContractArchitectureTest.php`, see "The guard library" below), no config test over the discovered listeners and commands, no row for the analyser's switches, no keyed-baseline script, no scheduled job, no dashboard row for open classes or for the CI duration, none of the rows `refactor-sets`, `formatter-rules` and `advisory-block`, and no row that counts a formatter rule's sites. Each of these is "not in the reference project" in the catalogs: the kit prescribes it and says how to build it.
- **The strictness the kit added later.** This project predates it. It has none of: the framework's strictness calls in one provider with their config test (the provider is not in this extract, and the project left the missing-attribute check off), the hermetic base test set-up, the two architecture presets, the 21 strictness rules in its formatter config (its `pint.json` is not in this extract), the bleeding-edge include in `phpstan.neon`, the `codingStyle` set and the framework sets in `rector.php` (which also does not read the framework version from the manifest), the advisory-blocking package, the manifest floor, the committed bot config, the six composer scripts, a browser smoke suite, a coverage floor with the goal of 95. Copy none of its configs for these: `templates/tool-configs.md` and the catalogs are the source.
- **Checks that came late or differ.** Its dashboard became a blocking step only after "zero"; its coverage gate uses an automatic target with a tolerance. The kit: the dashboard blocks from step 2 and the coverage floor is a committed number.
- **Not built, by the owner's decision.** Code owners, a guardian group and scheduled drift and update pipelines; the drift scan and the updates are run by hand there.

## The guard library

Each of these is a complete worked example: a detector class, a thin test with failing examples (each with the words the message must contain), passing examples with the near misses, and one real-tree case. The blind sides of `04-guards.md` C1 ("The standing blind-side list") are the checklist for the next guard you copy from them.

| Guard | Detector, test | Teaches |
| --- | --- | --- |
| A queued class | `tests/Support/Architecture/UniqueJobGuard.php`, `tests/Unit/UniqueJobArchitectureTest.php` | inheritance, alias and fully qualified spellings of one contract; the shape of every shape guard |
| The paths the guides name | `tests/Support/Architecture/DocumentedPathGuard.php`, `tests/Unit/DocumentedPathArchitectureTest.php` | the document spellings, a sandbox tree for the examples |
| The pipeline and its scripts (Bitbucket form) | `tests/Support/Architecture/CiContractGuard.php` and `CiSafetyGuard.php` (two classes, each under three rule groups), `CiExamples.php`, `tests/Unit/CiContractArchitectureTest.php` | what runs is derived from `pipelines.*`, never from step names; exact script lines, comments stripped; the owner exceptions are constructor arguments; every mutation of the example is checked to exist, so an example cannot silently change nothing. Its real-tree case needs `bitbucket-pipelines.yml`, `scripts/checks.sh` and `scripts/kit-config.sh` in the repository: this extract has none (its pipeline is another host's), so copy the guard with the pipeline. The other hosts change only the reader of the triggers |
| The words the guides must keep | `tests/Support/Architecture/GuideRulesGuard.php`, `tests/Unit/GuideRulesArchitectureTest.php`, `docs/guards/unique-job.md` | one table, two directions: a rule without a row, a row without a rule, words the guide lost |

The other guards in `tests/Support/Architecture/` are the same shape and carry their own examples; a few hold literal paths (listed above) and are to be copied for their idea only.

## Rules whose guard is not in the reference project

In a new run each of these is a counter from step 1 and gets its guard by the kit's `04-guards.md` C7.

| Rule (catalog row) | To converge |
| --- | --- |
| "HTTP clients" | a macro per external system, then the banned-call rule |
| "Iterating many rows" | `lazyById` or `chunkById`, then the banned-call rule |
| "Search": a case-sensitive match with a variable pattern | a decision per site (does case matter), then through the helper |
| "Locks": the banned-call rule per layer | through the lock class, then the banned-call rule |
| "Final by default" | per class: its tests stop doubling it or the facade in front of it; a concrete parent becomes abstract or its children are folded in first (`05-program.md` D8) |
| "Scheduled work": the lock expires at most at the task's interval | a decision per task (the longest honest run against the interval) |
| "Middleware around the next handler": the call shape | the guard class on the call shape |
| A test named after a class references that class (a candidate of `04-guards.md` C7) | converging means reading each file: a decision list (`04-guards.md` C7) |

The lessons of the earlier runs are in `record-source-project.md` in the kit's root. A run does not need it.

## The example data is invented

Every list in this folder that looks like an inventory (route names and middleware, rate-limit categories and limiters, controller verbs, role and permission names, queue names, stored class names, aliases of moved classes, translation keys, table names, host names, numbers of days) is a short invented one for an ordinary web shop. It keeps the shape the guard needs and says nothing about a real application. The tests cannot be run from this folder: the application they run against is not included.
