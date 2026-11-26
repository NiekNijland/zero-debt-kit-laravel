# Start prompt

Paste this in a new session in the target repository. Replace the two values in angle brackets. If the project has no ticket system, write the fixed prefix `NOTICKET` as the ticket; commits then read `NOTICKET refactor: ...`.

---

You are the lead of a code cleanup of this repository. The goal: every task in this codebase is done one way, that way is written down in `AGENTS.md` files with a file to copy, a guard in CI stops the old way coming back, and every technical-debt counter is at zero with nothing that lets it grow again. The method, the patterns and reference code are in the kit at:

`<absolute path to zero-debt-kit>`

Ticket for every commit and PR of this program: `<TICKET>`.

Do this, in order:

1. Read only the kit's `README.md`, `01-principles.md` and `02-step-1-discover.md`, and this repository's own `AGENTS.md` / `CLAUDE.md` and agent memory if they exist. Who decides what is in `01-principles.md`: the kit decides code patterns and structure, the repository (and the organisation above it) its own business, and I decide product, scope and exceptions.
2. **Ask me the questions of `02-step-1-discover.md` 1.1 before you measure anything**, in one round. If the ticket or the kit path above still reads as a placeholder in angle brackets, ask for it in the same round. The hand-off files live in the hand-off folder I name in that round. They stay outside the repository; what the repository must know is committed in step 3, and the plan is archived in it when the program closes. While I answer, read what step 1 uses of the rest: `03-conventions.md`, `04-guards.md` C1, C2, C3 and C6, `tools/README.md` (1.6 and 1.7 run the dashboard from a scratch copy and the mutation sample), `05-program.md` D1, D2 and D6, and of `catalog/` only the files of this repository's stack: `backend-laravel.md` for a Laravel backend, `frontend-react.md` for a React frontend, `tests-database-ci-docs.md` with either; any other stack reads `other-stacks.md` and, of the other three, only the rows and tables it points to. Read `07-process.md` and `10-hazards.md` before the first code change, not now.
3. Run **step 1** only. Change no code, open no PR. Produce the four hand-off files in the hand-off folder:
   - `project-profile.md`, filled in from what you find, not from what you assume;
   - `backlog.md`: every finding with an id, a count and the command that produced the count;
   - `zero-debt-plan.md`: the dashboard, the catalog mapped onto this project, the definition of clean, and the ten steps filled in for this project;
   - `CONTINUE.md`: state and next steps.
4. Come back with those four files, the start audit's findings, and only the questions that are really mine (`02-step-1-discover.md` 1.10), every owner decision the whole program will need, in one round (the questions of 1.1 were the round of facts before measuring): I answer nothing after step 1 that step 1 could have asked. One subject per question, with options, the kit's reasons and your proposal. Decide every detail yourself and record it with its reason. I sign the definition of clean; after that it only grows.
5. Then work through steps 2 to 10 of `05-program.md` in order: 2 Make the checks tell the truth, 3 Freeze the patterns, 4 Test foundation, 5 Layout, 6 Types, 7 Module hygiene, 8 Patterns, 9 Fill and trim, 10 Lock and audit. The program ends at step 10 with the definition of clean met; there is no earlier stopping point that counts as done. At the start of every step, re-read only that step's part of `05-program.md` D2 and the catalog tables it names; `CONTINUE.md` lists them. At the end of every step: show the exit artifact its "Exit:" line names and the re-measured dashboard. Stop for my go after steps 1, 3 and 10, and whenever a step's audit adds a counter that needs my decision or a question about behavior comes up (`05-program.md` D4); otherwise add the counters, report and continue. A step that changes source code is not done before its runtime check passes on a production-like environment. Keep the hand-off files current after every merge. Never invent scope: a new idea is a backlog row first.
6. When the program closes, write what this run learned back into the kit.

Rules that hold from the first minute: guards only shrink; no exception entry added by hand to make a build green; nothing that the repository or the organisation above it manages (a formatter config hosted by the organisation, a pipeline autofix commit, shared images, the deployer) is replaced or removed without my answer to a question that gave its reasons; never touch production; never print or store secrets; never force push; no push, PR, merge or message to anyone before I have seen the step 1 result.

---

## To continue an existing run

"Continue the `<TICKET>` cleanup. Read `CONTINUE.md` in `<hand-off folder>` first, then the kit's `07-process.md` section 1, then check reality (fetch, open PRs, CI state) before you trust any status in the notes."
