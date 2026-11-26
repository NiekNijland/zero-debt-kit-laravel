# Project profile: <project>

Filled in during step 1 from the repository and the hosting API. Mark each line `found` (with where) or `asked` (with who answered). Measured on `<branch>` at `<commit>`.

## Identity

- Repository and host:
- The branch pull requests target (the owner's answer, with the branch list and ahead/behind counts that were put to the owner), release branches, how each long-lived branch deploys:
- The command that fetches from the remote where the plain one fails (a moved host name or key), and the form of the remote the scripts use:
- Managed outside the repository, and kept (formatter config hosted as a URL: which URL, linter or refactoring config, shared pipeline images, autofix commit or merge-back bot, deployer, release flow with automatic merges: when releases happen):
- Long-lived branches that run the same checks, how far each is from the main branch, and what each does when a baseline lands (merge the main branch first, or regenerate on its own tip):
- Ticket for the program (or the fixed prefix `NOTICKET`), branch prefix:
- Ticket the dependency bot writes in its commit messages (`templates/tool-configs.md`, "Dependency bot"):
- The one password rule, as the owner decided it (`catalog/backend-laravel.md`, "Password rule"):
- Owner, reviewers, who decides what:
- Sibling repositories that share a contract (API clients, other services):
- The hand-off folder the owner named:

## Stack

- Backend language, framework, versions, and the distance to the catalog's version floor:
- Frontend language, framework, versions, and the distance to the floor:
- Test framework per language (PHP: Pest or PHPUnit, one), parallel or not:
- Static analysis, level, extensions:
- Test method spelling (step 1: read the formatter config, a hosted one included, for the rule on test method casing, and follow it; `catalog/tests-database-ci-docs.md`, "One spelling in a test"): <PHPUnit `test_snake_case` / `testCamelCase` / Pest `it`>, and the rule or file that decides it:
- Formatter, refactoring tool, their configs (and any config agents must not touch; where the formatter config is hosted by the organisation, that is named here and in the CI table, and is never copied into the repository):
- PHP extensions or system tools the developer host lacks and the pipeline image has (baselines are generated in the image):
- Are the agent guide files (`AGENTS.md`, `CLAUDE.md`) tracked in git, or ignored:
- Linter, type checker, unused-code tool, mutation tool:
- Database engine and major version in production (the value of `DATABASE_IMAGE` in `scripts/kit-config.sh`), and in the test suite:
- Package managers, private registries, and which commands need credentials (where they may run):
- Package roots, each with its manifest (the one `scripts/` folder sits at the repository root and its config paths list every root):
- Tools and packages the project uses instead of the catalog's for a need, and the step each is swapped in (`05-program.md` D2, "Tool swaps"):

## CI

| Step | Runs | Blocking | Whole tree | Duration | Notes |
| --- | --- | --- | --- | --- | --- |
| | | | | | |

- CI host, the skeleton in `ci/` that fits it, and the pipeline file that exists today (it is kept and extended, never replaced):
- Who can make a pipeline schedule, make the read token for the pipelines API and change the required checks of the main branch (the owner's three steps of step 2):
- Who owns a red scheduled run after the program closes (the repository's maintainer):
- Paths that skip CI:
- Autofix bots that push commits, and whether the owner kept them (the steps that may write, named for the contract test):
- Branches that deploy, each with its pipeline (the checks, tests and smoke run on every one) and whether the pipeline of a deploying branch is the deploy pipeline (then smoke sits in front of the deploy step):
- Does the pipeline merge the base branch first (Bitbucket and GitHub do: `CHECK_HEAD` is then never set; GitLab checks out the branch as it is):
- Price of a pipeline minute, what that means for pushes, and the cost of the runs of step 2 as told to the owner (`02-step-1-discover.md` 1.10):
- Does retargeting a PR, or merging one PR into another PR's branch, start a pipeline run:
- Where the error of a failed step is read when the log is short (the host's API field for a runner or image failure):
- Who owns the runner, and how that person is reached when a step is red for infrastructure reasons:
- What a fatal error in one worker of a parallel test run looks like in the result:

## Review and merge

- What merges a PR, and may the lead merge an approved green PR:
- How a PR merges: merge commit, squash or rebase (a squash or rebase gives the format commit a new hash; `05-program.md` D2, step 2):
- Does the host enforce the merge gate (branch protection, required checks, required approvals)? If not, what replaces it (who merges, on which evidence):
- Does a push reset approvals:
- Merge checks, default reviewers:
- Is the owner the only human reviewer:
- How many PRs are usually open, and when few are (for a check that fails every open PR, `07-process.md` section 10):
- Who announces to colleagues, and where:
- Freezes: periods or branches in which the program does not merge:
- PRs the program must never merge (hotfix, release):
- Reviewers are added: always / only with the owner's yes

## Release

- How the main branch deploys, how often:
- Who rolls back a release, how that person is reached, and how a rollback is done:
- Queue workers: do they restart with a deploy, and how long can old payloads live:
- What to watch after a deploy (error tracker and its query, queue dashboard, failed jobs):
- Preview environments, tags that skip them:
- Retention period of soft-deleted rows (asked in 1.10, where the project has any):
- Stored names: three releases (default) or the one-release variant (`05-program.md` D9), as the owner chose in 1.10:
- From where the production-like environment is reachable (the lead's machine, the pipeline's runners, only the owner) and the form of the runtime check that follows (`05-program.md` D4, point 5):
- The production-like environment for the runtime check (`05-program.md` D4): its cache store, queue and database engine, how a merge reaches it, where its deploy result is read, the pages to request, and the seeded test user the lead signs in as (where its secret lives, never the secret itself):

## Data hazards

- Tables or columns that store names from code:
- Queued classes (jobs, notifications, mailables):
- Polymorphic type columns, and the map of their aliases:
- Config files that import application classes:
- Classes chosen by config, classes behind a facade the tests mock, classes built from strings (`05-program.md` D8):
- Middleware that wraps the next handler in a transaction, a lock or a `try`:
- Generated files and how to regenerate them:
- Multi-tenant or multi-database set-up, and the isolation primitive:
- Tables too big for a plain schema change (the big-table list):
- Columns that hold secrets or personal data:

## Verification policy

- Allowed locally:
- CI only:
- How tests run locally if allowed (container, env), and how a worktree gets its dependencies and env file:
- Pushes per round:

## House rules found in the repository

(List them with the file they come from, sorted by `01-principles.md`, "Who decides what": the project's own business stays; a house rule on a code pattern or structure that differs from the kit's catalog goes to the backlog.)

## Differences from the kit

(Techniques this project uses instead of the PHP and React of the stack catalogs: per need of `catalog/other-stacks.md`, "Tooling in another stack", the one tool; for a stack without a table there, the table the lead built in step 1. Exceptions on a code pattern: only those the owner granted, each with its decision row.)
