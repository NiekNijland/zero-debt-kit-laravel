# Adoption guide

For the person who owns a repository and will hand the kit to an agent. The method itself is in the numbered files; this page is what you do around it.

## Who it is for

- A Laravel application, with or without a React frontend, that already has tests and static analysis (the kit assumes Pest, PHPStan, Rector and Pint) and still sees the same task done several ways.
- A team where agents write or will write code, and where "the reviewer remembers the rule" is no longer a plan.
- An owner who can sign a definition of clean and answer questions about product, scope and exceptions. The kit decides patterns and structure; you decide the rest (`01-principles.md`, "Who decides what").

It is a poor fit for a greenfield repository with nothing to burn down (the guards still help, the ten steps mostly do not), and for a stack other than Laravel and React: the method transfers, but nothing outside Laravel and React has been run (`catalog/other-stacks.md` maps the rows on paper).

## Two ways in

- **Minimal adoption**: the ratchet script and three guards, by hand. See "Minimal adoption" in `README.md`. A gate, not a finished cleanup.
- **The program**: an agent runs the ten steps. Install the plugin (`README.md`, "Install as an agent plugin") or give the agent the folder path and paste `00-START-PROMPT.md`.

## What to prepare

- A repository where the agent can run what the answers allow (tests, builds, analysers). What it may not run is recorded as "not measured", never as a count.
- The answers to the questions of `02-step-1-discover.md` 1.1: the branch pull requests target and how branches deploy; what the company or a platform team manages outside the repository; what may run and be installed; which commands need credentials and where they may run; where the hand-off files live (outside the repository); who merges; who announces moves to colleagues; any freeze; how pull requests merge; who rolls back a release; how the agent signs in on a production-like environment (a seeded test user, never a real person's credential) and from where that environment is reachable.
- A production-like environment. Every step that changes source code ends with a runtime check on one (`05-program.md` D4).
- A person or team for the code-owners rule over the guard files. The setting that requires that review lives on your host, not in the repository (`ci/CODEOWNERS.example`).
- A ticket for every commit and PR, or the fixed prefix `NOTICKET`.

## What the three gates ask of you

The agent stops for your go after steps 1, 3 and 10, and whenever a decision is yours.

1. **After step 1 (discover and define).** The agent has changed no tracked file. It brings four hand-off files (project profile, backlog, plan with the dashboard, `CONTINUE.md`), the start audit and every owner decision the whole program needs, in one round. You answer them and **sign the definition of clean**; after that it only grows.
2. **After step 3 (freeze the patterns).** The one-pattern table, the layout table, the test conventions and the review rubric are written, and every guard is in with its counted list. You check that the patterns are the ones you want to live with: this is where the kit's decisions become your repository's rules.
3. **After step 10 (lock and audit).** Every list is deleted, fresh agents have audited, `--final` has run and the runtime check has passed on the final commit. You accept that the definition of clean is met.

Between the gates you answer a question only when it is a product or behavior question (`05-program.md` D4) or a counter needs your decision.

## What it costs

The kit publishes no time estimate, and this guide does not invent one: it has no dates, estimates or priorities (`README.md`, "Limits"). What the program asks for is written down: one standard with ten steps and no earlier stopping point that counts as done, a pull request per unit of work, a runtime check per source-changing step, and a pipeline run as the cost of every mistake when nothing runs locally (`02-step-1-discover.md` 1.1). Size your own repository from the dashboard that step 1 produces.

## After adoption

Counters only go down; at zero the list is deleted and CI fails if the old way comes back. The scheduled run keeps the end state (`debt-dashboard.sh --final`). To see whether a project's copy of the scripts still matches the kit, run the doctor: `bash scripts/doctor.sh --kit <path to the kit>` (`UPGRADING.md` says what to do with the result). When the program closes, the run's lessons go back into the kit: see `.github/ISSUE_TEMPLATE/run-report.md` for what is useful to send.
