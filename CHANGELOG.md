# Changelog

All notable changes to the kit. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/): a change to a script's behaviour or to the config block's keys is at least a minor
version, and every release says which scripts changed, because a project's copies are guarded files (UPGRADING.md).

No version is tagged yet; v0.12.0 will be the first tag. The versions before 0.9.0 are reconstructed from the history of the repository and are grouped by theme.

## [Unreleased]

### Added
- `01-principles.md`, "Kept prototypes" (finding 50): a folder of design prototypes the owner keeps on purpose makes `lint-config-ignores` shrink-only by a decision record, with no end date; first try to leave the folder out of the lint scope. No new mechanism: `SHRINK_ONLY` and the record, as for an abandoned package. `README.md`, `tools/README.md` and `templates/zero-debt-plan.md` point at it.
- `07-process.md` section 12, "Where the tag sits" (finding 49): a `[ratchet: <row>]` tag counts at the start of any line of a commit message, the subject line included, and nowhere else; a squash keeps only the tags in its message. The claim of the finding that the subject never counts was wrong: only a tag in the middle of a line does not.
- Proof scenarios `RX19b` to `RX19e` and `DX30` to `DX39`.
- `tools/ci-ratchets.sh --self-test` tests the exclude pathspecs of a row: a built-in case in a file the glob names, and the case syntax `<row>@/<path> <count> <text>` for a project's own excluded folder (finding 30). Changed scripts: `ci-ratchets.sh`.
- Proof scenarios `NX01` to `NX16` (`tools/proof/scenarios.d/round2.sh`).
- `10-hazards.md`, "Before a repository can be public": the question in step 1.1, the scan of all refs and of personal data, and that a history rewrite on an existing GitHub repository leaves the old commits reachable through the pull-request refs and by SHA (findings 48, G-30).
- `08-builder-rules.md`: a per-builder `TEST_TOKEN`, named containers removed with `docker rm -f`, one `node_modules` per environment, the co-author line from the brief, undo of a break from a copy, enum-case clashes in a codemod, and "One machine" (at most three agents in total, one or two full suites, a commit after every file) (findings 28, 31, 33, 35, 38, 41, 44, 46).
- `07-process.md` section 11: every job failing in one or two seconds with no log is a quota or billing stop, read the check-run annotations; `04-guards.md` "GitHub Actions cost": the minutes of one pull-request run in the profile, the levers that remain, and why the skeletons do not skip jobs by changed path (findings 47, G-29).
- `catalog/frontend-react.md`: one definition of a swallowed catch (finding 37). `catalog/backend-laravel.md`: `mixed` only at named entry points (finding 27). `tools/README.md`: a sampled mutation run for a test-only branch, the guard-folder exclusion of the three PHP rows, the heredoc heading and the `8bit` spelling (findings 34, 42, 43).

### Fixed
- `ci-ratchets.sh` says in its failure message that a tag counts only at the start of a line and that a squash keeps only the tags in its message, and names the commits that hold a `[ratchet: ...]` tag inside a line (finding 49). The script does not accept the tag mid-line: it would match a commit that only quotes one. Changed scripts: `ci-ratchets.sh`.
- `doctor.sh --apply` no longer drops a config key only the project has (finding 51, `COVERAGE_FLOOR_FILE` read by the project's own `min number` row): it is carried at the end of the config block, listed as the project's addition when a row, a task or the body reads it, and as a divergence otherwise. The self-test cases (the `CASES` and `EXTRA` fixtures) of a row the project defined itself are the project's: not compared, kept in place of the kit's. A config value or row that equals an older kit default is still replaced by the kit's, but `--apply` now prints each one as a conflict with the project's value and the kit's, and says that a value with a trailing comment is the project's decision. The tag lines after `--apply` come from the table before and after. `UPGRADING.md` says which values migrate. Changed scripts: `doctor.sh`.
- `lint-config-off` reads the severity position only: the `'warn'` inside an `allow` option of a rule is no longer counted (finding 36). Changed scripts: `ci-ratchets.sh`.
- `CHECK_BASE=origin/main` is read as `main` (finding 39). Changed scripts: `kit-config.sh`, `checks.sh`, `prepush.sh`, `mutation.sh`.
- One form for dispatching an event, `Event::dispatch(new <X>Event(...))` with no `Dispatchable` trait, because the Laravel code-quality set rewrites `<X>Event::dispatch()` into the `event()` helper the analyser bans (findings 26, 32). The shared Inertia fake is no hit of the per-file mock count (40); the docs name the coverage file and the row, not the number (45).
- The self-test case count in the README and `04-guards.md` matches what the script prints.
- The skeleton database health command is the TCP form `pg_isready -h 127.0.0.1 -U <user>` (P8-1): the socket form answers during the image's first-run init server and the first job connection fails.
- Docs say to drop `/.github export-ignore` from a Laravel `.gitattributes` when the repository is used as a template (P8-2).
- `reference-project/files-to-copy.md` and `docs/starter-variants.md` name what the starter has: the ten classes of the GitHub guard, the `tests/Support/Ci/` folder, no switch count of its own (P7-1, P7-2).
- `04-guards.md` C4: the reader of secret names must also find `secrets['NAME']`, `secrets[format(...)]` and `toJSON(secrets)` (P7-3).
- `starter-canary.yml` lost the step that put task lines back; the doctor keeps them (P7-4).

## [0.12.0] - 2026-11-26

The scope is narrowed to what has been run: a Laravel (PHP) backend with a React frontend. A Laravel project without a frontend
source folder stays supported. Other stacks are out of scope; nothing is promised or proven for them.

### Removed
- `catalog/other-stacks.md` and every reference to it.
- In `tools/README.md`: the section "Another stack", with the TypeScript-backend example block, and the sentences on a project
  without PHP. The block for a Laravel project without a frontend source folder stays, under its own heading.
- The proof scenarios that proved only a TypeScript backend: `T03`, `T03b`, `T05`, `T06`, `T06b`, `T08`, `T08c` and `T12`.
- The sections and sentences that said the method or the tools work for any stack (README, FAQ, adoption guide, catalogs,
  templates, `05-program.md`, `01-principles.md`, `04-guards.md`, `06-lock-and-audit.md`).

### Changed
- README "Who it is for" states the scope once. No script changed its behaviour; `tools/ci-ratchets.sh` lost a comment line.

## [0.11.0] - 2026-11-26

The reference is the running starter, not an extract. No script of `tools/` changed; the proof gained and lost scenarios.

### Changed
- `reference-project/` is a pointer to the strict Laravel starter (<https://github.com/NiekNijland/zero-debt-laravel>): `README.md`,
  `files-to-copy.md` (the starter's paths and what each is for, read at the starter commit it names) and a few examples that do not
  run (`examples/php`: the transaction middleware, the counted list, the stored-name register guard; `examples/frontend`: the
  eslint, knip and tsconfig configs and the frontend `AGENTS.md` files). The anonymized extract of 294 files is gone: its guards the
  starter has now come from the starter, which runs them; the rest is in the backlog `docs/starter-variants.md` (multi-tenant,
  frontend, the Bitbucket and GitLab readers of the CI contract, the older and register-form guards, three coverage tests). Every
  reference in the docs, the catalogs, the templates and the proof was updated; a "not in the reference project" row that the
  starter now has (the config test, the strictness provider, the hermetic base set-up, the coverage floor and the strictness rows)
  points at the starter's file.
- Proof: `tools/proof/fixtures/ci/CiHostExamples.php` is the fixture of the scenarios `CX27`, `PF03` and `RG06` (the skeletons with
  their placeholders filled). Those three had read a path that no longer held the file and were skipped silently; they run now. The
  scenarios over the starter's guard folder (`RG03`, `RG04`, `RG08`, `RG09`, `TX51`) run when `STARTER_DIR` names a checkout of the
  starter. `RG07`, which read the Bitbucket-era GitHub guard, is gone. `RG01` and `RG02` read the examples.
- Docs: the opening claims say what the guards enforce (a counter only goes down; an override is a tagged commit that a code owner
  reads); the at-zero rule is stated once, with its `OVERRIDE_AT_ZERO` exceptions; the mutation tools are described as verified.
- The plugin manifest has a version, the marketplace a description; the proof scenario `DOCX01` to `DOCX07` checks the numbers the
  docs claim.

### Added
- `docs/starter-variants.md`: the backlog of what the starter lacks.
- `.github/workflows/starter-canary.yml`: a proposal (not run): the starter checked out, the kit's scripts applied to it by the
  doctor and its static and guard parts run, nightly and by hand.

## [0.10.0] - 2026-11-26

The audit-fix round: the tools were reviewed again, their defects fixed and proven, and the kit got an upgrade path.

### Added
- `tools/doctor.sh`: compares the scripts in a project's `scripts/` with the kit's copies while ignoring the config block, the
  ratchet table rows and the task commands of `checks.sh`; reports identical, config-differs, body-differs, missing and
  project-only per script; names the kit version and commit the copies match; `--upgrade-plan` and `--apply` bring the bodies to
  the kit's version and keep the project's values. Proven by the scenarios `DX01` to `DX23`.
- `VERSION`, this changelog and `UPGRADING.md`.
- `tools/proof/proof.sh`: `PROOF_ONLY=<regex on the scenario id>` runs one area, `PROOF_LIST=1` lists the ids. A run without
  either behaves as before.
- The kit's own CI, `.github/workflows/proof.yml`: lint (`bash -n`, `php -l`) and the proof suite on Linux and on macOS (bash 3.2).
- `ci-ratchets.sh --self-test`: the expressions of the ratchet rows against their fixture, so a row whose regular expression
  stopped matching fails loudly; it runs in the gate part of `checks.sh`.
- A GitHub Actions skeleton in a file of its own (`ci/github-actions.yml`): merge-first, concurrency, one aggregate required
  check, services with environment and health checks, the container image pinned by digest; the dependency bot keeps pinned action
  digests current.
- Contract point 22 (code owners): the code-owners files are guarded files by default, `ci/CODEOWNERS.example`, and a contract-test
  reader for GitHub workflows, GitLab pipelines and the code-owners point.
- At-zero overrides (`OVERRIDE_AT_ZERO`), `--init` for the keyed baseline, and a hazards section for a tool upgrade.
- Proof scenarios `RX` (ratchet script fixes), `FX` (CI follow-ups), `TX01` to `TX51` (audited defects), and for the skeletons.

### Changed
- `ci-ratchets.sh`: attributes, commented-out switches, quoted names, tag position, fork fetch and the missing spellings are read
  correctly; the self-test fixture and the fetch fallbacks no longer count as skipped CI steps.
- `mutation.sh` resolves the base from `CHECK_BASE`, then the host variables, then the default branch.
- `ci-duration-minutes` is the median of the last ten successful push runs.
- The README has a minimal adoption path, lists every guard and ratchet, and says what is proven.
- Reference project: the guards take their folders and files as arguments, read injected collaborators and the container's string
  forms, and every guard test file is in the group `arch`; the PHP floor is 8.4.
- Pull requests merge first on Bitbucket and GitHub (the merge ref is what is tested); a merge queue is not supported
  (`ci/github-actions.yml` says why).

### Fixed
- `audit-gate.sh` keeps its "printed no JSON" message for empty input.

## [0.9.0] - 2026-11-26

The tools audit: every script of `tools/` was reviewed; the defects found were fixed and each has a proof scenario.

### Changed
- `audit-gate.sh` rejects truncated or foreign JSON, uses the UTC date, counts one id per entry and deduplicates ids.
- `debt-dashboard.sh` validates its expressions, counts last lines, takes one-number command rows, shows lowered goals and skip
  reasons.
- `keyed-baseline.sh` needs a position for `tsc` findings, ignores `knip` lines before a heading, and `--write` refuses growth.
- `mutation.sh` includes renamed files and non-ASCII paths; there is one Stryker floor mechanism.
- `map-normalise.sh` sorts once and respects name boundaries; `move-ts.mjs` checks before moving and handles non-ASCII paths, CRLF
  and more specifier forms.
- `ci-commit-messages.sh` no longer lets single-parent `Merge` titles and open-ended `Revert` titles pass.
- `ci-parallel.sh` validates its tasks and limits and stops its watchdogs; the reference copy is the kit's.
- CI scripts: one fetch in `prepush.sh`, one argument loop, phantom part names rejected, the base fetched only by the parts that
  read it, the scan range ends at `CHECK_HEAD`.
- Reference project guards read more of the language: queued-class limits through parents and traits, injected dispatchers,
  loggers and transactions, joined queue names, `whereLike`, rate limiters and form requests.

### Added
- The proof suite reads scenario files from `tools/proof/scenarios.d/`.

## [0.2.0]

The reference project and the hazards of the first complete cleanup.

### Added
- Reference project guards: register, closed-class, method-size and module-layout guards.
- The adapter layer and the autoload-conformance guard row.
- Hazards of the closing rounds: an advisory at the bottom of a chain, caps that equal their rows, any-of abilities, queued class
  formats, git and shell traps, merge waiters.

### Changed
- A pull request is ready only when every review thread is answered and resolved; approvals are chained after a retarget.

## [0.1.0]

### Added
- The kit: the principles, the ten steps, conventions, guards, program, lock and audit, process, builder rules, reviewer brief and
  hazards documents; the scripts of `tools/`; the CI skeletons; the catalog and the templates.
- Pull requests are chained by default and merged from the bottom with the source branch closed.
- A README that opens with the problem: architecture tests and ratchets as the CI quality gate.

