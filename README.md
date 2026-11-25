# zero-debt-kit-laravel

Green CI does not mean a codebase has a shape. You can have high coverage and PHPStan at level 8 and still end up with three ways to authorize a route, four ways to write a test helper and a new pattern with every pull request. A linter checks lines, not structure. With AI agents writing a growing share of the code, the drift gets faster.

This kit makes CI the quality gate for structure. It does that with two mechanisms:

- **Architecture tests** decide, per task, the one allowed way: where a class lives, what it may depend on, how a controller or a test is shaped. Code that does it another way fails the build, with a message that says what to do instead.
- **Ratchets** are counters for everything that is still wrong: old violations, suppressions, skips, oversized files, baselines. A counter may only go down. Nothing can be added to make a build green, and at zero the list is deleted and CI fails if it comes back.

Together they are the guards. The result: anyone, including an agent working unattended, can contribute, and when CI is green the code fits your design patterns. The old way cannot come back, and the rules do not depend on a reviewer remembering them.

## What the gate looks like

A class in a place the layout table does not allow. The architecture test names the rule, the place and the fix:

```text
FAIL  keeps every class of a module in the folders of the module table of app/AGENTS.md
place app/Modules/Orders/Loose.php: no folder of the module table in app/AGENTS.md holds a class here.
A class sits in <Domain>/<Folder>/[<Subdomain>/]<Class>.php, never in the module root or a folder the
table does not name, as the table says.
```

A suppression added to make a static-analysis error go away. The ratchet row is at zero, so the build fails, and a row at zero never takes an override:

```text
ratchet                                 may    base    head  change  result
analyser-baseline                      down       0       0      +0  ok
analyser-ignore                        down       0       1      +1  FAILS

A ratchet moved the wrong way, or a row was redefined or removed. A row that is at 0 never takes an override: fix the entries. Other rows can be explained in a commit that touches that row's files, or this script for a redefined or removed row:
  [ratchet: <name>] <reason>
```

The first output is the message of a guard in `reference-project/`; the second is what `tools/ci-ratchets.sh` prints when a suppression is added (the numbers are illustrative).

## What it is

A program you hand to an AI agent in your repository. It measures the codebase, writes down the one way per task (with a file to copy), puts a guard in CI for every rule, and burns the old way down to zero in ten steps. You sign off at three gates and answer the questions that are yours; the agent does the rest. The end state: every task is done one way, the way is written down, a guard stops the old way coming back, every debt counter is at zero, and nothing lets a counter grow again.

It is one standard: there is no lighter version of done. If you only want a single guard, take it from `reference-project/` and `04-guards.md`; you do not need the whole program for that.

## Who it is for

Teams on Laravel and React (Pest, PHPStan, Rector, Pint) that already have decent coverage and static analysis and still see patterns drift, especially when agents write code. The method and the shell tools work for any stack; the catalogs, the reference project and the tool configs are written for Laravel and React. `catalog/other-stacks.md` maps the rows to other stacks, but nothing outside Laravel and React has been run.

It is proven on one real cleanup of one Laravel and React application; the lessons of each run are written back into the kit. The kit decides the patterns and the structure, and only techniques differ between projects (`01-principles.md`, "Who decides what").

It is generic: it holds no organisation-specific rule, name, address or token. What an organisation manages for itself (a shared formatter config, shared pipeline images, an autofix commit) is asked in step 1 and stays the organisation's.

## How to use it

1. Give the agent this folder's path. Start a session in the target repository and paste `00-START-PROMPT.md`.
2. The agent asks the questions of step 1 that it cannot answer from the repository, then measures, maps the catalog onto the project and audits. No code changes. You sign the definition of clean and answer every question that is yours, all in step 1.
3. The agent works through steps 2 to 10 of `05-program.md` and stops for your go after steps 1, 3 and 10, or when a decision is yours. A step that changes source code ends with a runtime check on a production-like environment.

Step 1 changes nothing in the repository. It produces four files: a project profile, a measured plan with a dashboard of every counter, a backlog and a hand-off note.

## The ten steps

1 Discover and define · 2 Make the checks tell the truth · 3 Freeze the patterns · 4 Test foundation · 5 Layout · 6 Types · 7 Module hygiene · 8 Patterns · 9 Fill and trim · 10 Lock and audit

## About the reference project

`reference-project/` is an anonymized extract of an application's guards, configs and guides, rewritten onto an example domain (a small web shop): the names in it are illustrative, the lists and guides are shortened, and the code is not a runnable application. Read it for the shape of a guard, not for a product. Its `README.md` lists where it falls short of the kit.

## Files

| File | What it is | Read when |
| --- | --- | --- |
| `00-START-PROMPT.md` | the prompt to paste | by the owner |
| `01-principles.md` | the goal, who decides what, the rules that never bend, the definition of done | first |
| `02-step-1-discover.md` | step 1: ask, discover, map, measure, audit, define clean | first |
| `03-conventions.md` | the way per task; the documents to write; the layout; decisions | in step 1 (mapping) and step 3 (documents) |
| `04-guards.md` | one mechanism per kind of rule, with the standing list of blind sides; the ratchet; the dashboard; CI shape; baselines; candidate guards | C1 and C6 in step 1; all of it in steps 2 and 3 |
| `05-program.md` | the ten steps with their exits, PR shape, end-of-step checks, size, closing classes, moving classes in three releases | D1, D2 and D6 in step 1; all of it before step 2; then, at the start of each step, that step's part of D2 and the catalog tables it names |
| `06-lock-and-audit.md` | deleting lists, the audit and its end rule, keeping zero, closing the program | step 10 |
| `07-process.md` | roles, lifecycle of a change, comments, conflicts, stacking, reading a red step | by the lead, before the first code change |
| `08-builder-rules.md` | rules for an agent that builds one theme | by a builder, with its brief and nothing else |
| `09-reviewer-brief.md` | cold reviewer with the break sample, object-level access checklist, auditor | reviewers and auditors |
| `10-hazards.md` | the traps, each ending in what to do | before the first code change |
| `record-source-project.md` | optional reading: the lessons of the earlier runs and the instruction each became | never needed for a run |
| `catalog/` | `backend-laravel.md`, `frontend-react.md` and `tests-database-ci-docs.md`: per task the one way, how to count the old way, and the guard; `other-stacks.md`: every one of those rows for any other stack | in step 1 (the mapping of 1.5) only the files of the project's stack: a stack other than Laravel and React reads `other-stacks.md` and, of the other three, only the rows and tables it points to. Then per step, the tables that step names |
| `templates/` | `project-profile.md`, `zero-debt-plan.md`, `backlog-and-continue.md`, `repo-docs.md` (the `AGENTS.md` set, decision record, review rubric, PR template), `pr-description-and-brief.md` (with the move map format), `tool-configs.md` (formatter and refactoring-tool configs to copy) | when creating them |
| `tools/` | `ci-ratchets.sh`, `debt-dashboard.sh`, `debt-caps.json`, `kit-config.sh`, `ci-commit-messages.sh`, `ci-parallel.sh`, `keyed-baseline.sh`, `audit-gate.sh`, `mutation.sh`, `map-normalise.sh` and `move-ts.mjs` (what each does: `tools/README.md`), and `tools/proof/proof.sh`, which proves every script in scratch repositories. The reference project's originals of the ratchet script, the dashboard, the caps file, the commit-message check and the parallel runner are in `reference-project/scripts/` | step 2 |
| `ci/` | `checks.sh`, the one command that the pipeline's checks step (one step per part) and a developer both run, `prepush.sh`, which runs every part side by side before a push, and the pipeline skeletons `bitbucket-pipelines.yml`, `github-actions.yml` and `gitlab-ci.yml`; a repository that has a pipeline adds their jobs to its own file | step 2 |
| `reference-project/` | guards, configs and `AGENTS.md` files of the reference project, a Laravel and React application, as worked examples on an example domain (an anonymized extract, not a runnable application). Its `README.md` lists where that project falls short of the kit | when building a guard: the `README.md` first |

## Limits

- No dates, estimates or priorities: order comes from dependencies.
- It does not decide product questions and does not touch the ticket system.
- `reference-project/` is another project's end state. Copy the idea and the shape, after reading `reference-project/README.md`; never paste a guard that names classes the target does not have.
- The scripts in `tools/` name no project: each starts with a config block the project fills in. What each needs on the runner and on a developer machine is in `tools/README.md`.
- The kit is self-contained: nothing outside this folder is needed.

## License

Released under the MIT License (see `LICENSE`).
