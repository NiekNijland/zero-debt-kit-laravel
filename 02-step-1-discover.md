# Step 1: discover and define

There is one standard: the definition of clean of `01-principles.md`, every counter at zero, all ten steps. Nothing in step 1 asks how far to go. No code changes, no PR, no push. The output is the four hand-off files (see the start prompt), the start audit, a definition of clean for the owner to sign, and a short question list.

## 1.1 Ask first

Before measuring, ask the owner in one round:

- **Which branch do pull requests target**, and how each long-lived branch deploys. List every long-lived branch with its ahead and behind counts against the others (`git rev-list --left-right --count a...b`) and ask. Never propose a branch from the deploy pipeline or the host's branching model alone: the branch the team actually works on may be another one. The checks, tests and smoke runs stand on every branch that deploys (`04-guards.md` C4), and this answer sets the base branch of everything that follows.
- **What is managed outside this repository** by the company or a platform team: a formatter, linter or refactoring config hosted as a URL, shared pipeline images, an autofix commit or merge-back bot in the pipeline, the deployer, a release flow that merges automatically (release or merge-back pull requests conflict with the program: ask when releases happen, so step merges are planned between them). None of it is replaced or removed. For each one that conflicts with a kit rule, put the question with the kit's reasons now, before any pull request: the kit asks only that the check runs on the whole tree and blocks.
- **What may run and be installed here**: test suites, builds, analysers at a higher level, a secret scanner, a mutation tool. What is not allowed is recorded as "not measured" with the reason; never presented as a count. The answer is also the verification policy for builders later (`08-builder-rules.md`, "Checks"). Say what it costs: with nothing run locally, every mistake costs a pipeline run.
- **Which commands need credentials** (a package registry, the hosting API) and where they may run. The lead never reads a credential.
- **Where the hand-off files live.**
- **May the lead merge a PR that is approved and green**, or does the owner merge? Is the host's auto-merge on a green pull request with its one approval allowed (it removes the longest wait of the program)?
- **Who announces to colleagues, and where**: a big move, a check that fails every open PR (`07-process.md` section 10).
- **Is there a freeze**: a period or a branch in which the program must not merge?
- **How PRs merge**: a merge commit, a squash or a rebase (`05-program.md` D2, step 2), where it cannot be read from the host.
- **Who rolls back a release**, and how is that person reached? Step 5 needs the name (`05-program.md` D9).
- **How the lead signs in on the production-like environment**: a seeded test user made for the program, never a real person's credential (`05-program.md` D4). Where the only sign-in is one the lead cannot have (a single sign-on of the organisation), the fallback is named now: the owner does the pages the lead cannot reach, or a token principal is seeded.
- **From where the production-like environment is reachable**: from the lead's machine, from the pipeline's runners (a firewall may refuse them), or only by the owner. The runtime check of every step needs one of the forms in `05-program.md` D4, point 5.
- **The command that fetches from the remote**, where the plain one fails (a moved host name, a key): it goes in the profile, and the scripts then fail, never fall back to a stale copy, when a fetch fails in a pipeline run.

The answers go in the project profile.

## 1.2 Get current

- Fetch every remote; work from the tip of the branch pull requests target (the owner's answer of 1.1, not a guess from the deploy pipeline).
- Measure in a detached worktree of that tip (`git worktree add --detach <scratch path> origin/<main>`). Never switch, stash or clean the owner's checkout: it may sit on another branch with uncommitted work.
- If sibling repositories share a contract with this one (an API, a client app), fetch those too. A "test-only" route is not dead when the client app calls it.
- Read the repository's `AGENTS.md`, `CLAUDE.md`, `README`, contribution docs, decision records and the agent memory. List every house rule, and sort each by `01-principles.md`, "Who decides what": the project's own business stays; a rule about a code pattern or structure that differs from the catalog is a backlog row. Where a team rule file exists (for example a layer or naming rule file), make one table now: team rule, the catalog row it meets, which wins. The proposal is "the catalog wins unless you say otherwise", and the table goes to the owner once, in 1.10, not piece by piece while the guides are written in step 3. Every layer statement of that file is later either a sentence of the guides with its guard, or struck by a decision row (`03-conventions.md` B2).

## 1.3 Fill the project profile

Use `templates/project-profile.md`. Find each answer in the repository or the hosting API; ask only what cannot be found.

## 1.4 Inventory what is enforced today

Three tables:

1. CI steps: check, blocking or not, whole tree or changed files, duration.
2. Existing guard tests and lint rules: what each enforces, whether it has an exception list, whether that list can grow, and which other spellings of the same construct it does not see (`04-guards.md` C1).
3. Config strictness: analyser level and extensions, linter presets and which rules are `warn` or off, type-checker flags, refactoring-tool sets and skips.

Call out two classic gaps explicitly: checks that only look at changed files (debt exists unseen), and "no new offenders" tests whose list anyone can extend.

For table 3, print the effective configuration of each tool (the linter's resolved config for a source file and a test file, the analyser's dumped parameters) and compare it with what the file appears to say: a merge inside a config file can silently switch rules off.

## 1.5 Map the catalog onto the project

The patterns, the layout, the limits and the strictness are the kit's (`catalog/`); step 1 does not propose them. It writes down, in `zero-debt-plan.md`:

- per catalog row: the ways the codebase does the task today and how many sites each has. Look especially at the newest code and at places where a bug class keeps returning;
- the domain list: which modules the project will have, and which of today's folders goes into each (`03-conventions.md` B3);
- tasks the catalog does not cover, each with the way chosen by `03-conventions.md` B1;
- the rows that apply in another technique's form (`catalog/other-stacks.md`), with the project's mechanism for each;
- every place where the project would need an exception. An exception is a question for the owner (1.10), never a silent variant.

This must exist now, because the measurements and the definition of clean depend on it.

## 1.6 Measure

Run every count in `catalog/*.md` that applies and record the number with the exact command. Add the distance to the version floor per platform part (the catalog's "Version floor per row"), the number of entries of every exception list of 1.4, and the duration of each CI step.

**The dashboard runs from a scratch copy**, so the owner signs the numbers the scripts print from step 2 on (`01-principles.md` rule 8). Copy `tools/*.sh` and `tools/debt-caps.json` to the scratch folder, fill in the config blocks there (`tools/README.md`), with `CAPS_FILE` in `kit-config.sh` as the absolute path of the scratch caps file, add to the scratch table the rows the project adds under a fixed name whose subject exists in step 1 (`04-guards.md` C2: the floors, `strict-types-missing`, `unused-ignore`), and run `bash <scratch>/debt-dashboard.sh --report --details` in the detached worktree. Every counter that will become a cap is measured in a clean clone with the pipeline's own install before its number is signed: a counter measured in a working container can differ from CI's. Every measured row above zero reads OPEN, against a cap of 0. The `cap-` rows read UNKNOWN until step 2, because they read the caps file from a commit; so do `ci-skipped-steps` in a repository without a `scripts` folder, and the floor rows, whose files step 2 creates. A count the scripts have no row for is made by hand, by the rules below.

**The test rows match today's folders as well as the kit's.** `BEHAVIOR_CLASSES`, `SUPPORT_CLASSES`, `FRONTEND_LOGIC` and `FRONTEND_COMPONENTS` name the kit's target folders. For each folder of the mapping of 1.5 that will be sorted into `Actions`, `Services`, `Jobs`, `Listeners`, `Commands`, a support folder or a frontend layer, add today's path to the expression as an alternative. The number the owner signs is then the real one, and a move in step 5 or 8 changes no row; the PR that completes those moves removes the alternatives (`05-program.md` D2, step 5).

- Measure on the tip of the main branch; write the commit hash at the top of the plan.
- Count tracked files only (`git grep`, `git ls-files`): a working tree holds generated and ignored files that are not debt.
- **A count of 0 is proven**: list the files the pathspec matches (`git ls-files <pathspec> | wc -l`) before the zero is written down. A pathspec with a glob in the middle (`app/*/Controllers`) matches no file without a trailing `/*`, and the count reads 0 in silence.
- A grep count is a text match; say so when it includes legitimate uses. Test each regex against a hand count of one file (`04-guards.md` C6); where a construct spans lines, a parser count is the accurate one.
- Raising a strictness level to see the cost is a measurement: use a scratch config outside the repository, never edit the repository config in this step. A refactoring-tool run with a scratch config outside the working directory takes its paths from the command line, not from the config (from the config it reports 0 files); distrust a whole-tree run that ends in seconds.
- **Measure what the kit's configs would change**: the formatter's test mode with the kit's config, and the refactoring tool's dry run with the kit's sets. The numbers say whether step 2 needs a whole-tree format commit at all. Where the organisation's own config is kept (1.1), measure with that one.
- **Count the methods over the limit by parser** (the dashboard has no row for them; the method-length guard of step 3 counts them from then on). The file counters and that guard share one scope.

## 1.7 The mandatory rows

Six things are part of every run and are not put to the owner: they are guards, and the kit builds guards (`01-principles.md`, rule 2). Measure where each stands today:

| Area | Measure | What the program installs |
| --- | --- | --- |
| Object-level access | every entry point that takes a record id (a route, a component action, a console command, a job payload), and which have a test for someone else's record | the sweep in step 8 with the checklist in `09-reviewer-brief.md`; a coverage test over each list of entry points (`catalog/tests-database-ci-docs.md`, "Tests (backend)") |
| Secrets | the secret scanner (gitleaks) over the tree and the history, with its output redacted; run it before dependencies are installed, or filter the report to `git ls-files` (`vendor`, `node_modules` and a copied env file are not the repository) | the same scan on every pull request from step 2 (`catalog/tests-database-ci-docs.md`, "Secrets") |
| Runtime | is there any automated check against a running instance, and does it use the real cache, queue and database engine | the smoke suite, as the first PR of step 2 (`catalog/tests-database-ci-docs.md`, "Smoke suite") |
| Local developer loop | is there one command that runs what CI's checks step runs | that command from step 2, `bash scripts/checks.sh` (`04-guards.md` C4) |
| Can the tests fail | a mutation run on a sample of ten behavior classes (`tools/README.md`, "Mutation"); the tool is installed in the detached worktree only, and nothing is committed | from step 2: the mutation tool on the whole tree in the scheduled job, with its minimum score in `scripts/mutation-floor.txt` at the measured value, held by the `min` row `mutation-floor`; from step 9: the same tool on the changed files of every pull request. The reviewer's break sample stays beside it (`09-reviewer-brief.md`) |
| Contracts between repositories and with external systems | each external HTTP client, and each API a sibling repository calls or serves | a fixture test per client and per such API ("Contract with a sibling repository" in the same catalog) |

## 1.8 Start audit

Hand the draft dashboard, the mapping of 1.5 (without it the auditor cannot judge what is uncounted) and the repository to an auditor subagent that did none of the measuring (`09-reviewer-brief.md`, auditor, start variant). Its one question: "what debt in this codebase does this dashboard not count?" Every finding becomes a counter with a command. In a session that cannot start subagents, write the auditor brief with the three inputs to a file in the hand-off folder and ask the owner to start a fresh session from it (`07-process.md` section 0). The lead never audits its own measuring.

## 1.9 Write the backlog, the plan and the definition of clean

- `backlog.md` (from `templates/backlog-and-continue.md`): one row per finding and one per dashboard counter, with an id by area, a count, the command, a status mark, and the step that handles it. The backlog is empty only at zero.
- `zero-debt-plan.md` (from `templates/zero-debt-plan.md`): the dashboard, the mapping of 1.5, the dependency notes, the ten steps filled in for this project, the decisions table.
- **Definition of clean**: section 4 of the plan, as runnable checks. The owner signs it. After that a counter is only added; dropping or redefining one is a decision row that shows the number under both definitions.
- Choose the size (`05-program.md` D6) from the measurements.
- Map every backlog item to a step before step 2 starts. An item that fits no step is a gap in the plan: add what is missing to the step's row, or close the item with its reason.

## 1.10 Questions for the owner

Filter with: "could a good product owner choose this without the owner?" If yes, choose and record it. What remains is:

- **the version floor**: the platform upgrades step 2 needs (`05-program.md` D2), with the distance measured in 1.6. The question is whether to upgrade; a no means the program stops at the rows the installed versions can carry, and each row it cannot reach is a decision row;
- where a PHP project has tests in both Pest and PHPUnit: which one stays (`03-conventions.md` B4);
- each exception found in 1.5, and each way chosen for a task the catalog does not cover where it changes behavior;
- the table of team rules against catalog rows (1.2);
- each thing the organisation manages that conflicts with a kit rule (1.1), with the reasons;
- each tool swap that changes stored data or what a user is allowed (`05-program.md` D2, "Tool swaps"), with its migration plan;
- behavior changes (a default rate limit on every read, a 404 instead of a 403, removing an auth mechanism, deleting a flag that is on everywhere);
- the one password rule: what it demands of a new password (`catalog/backend-laravel.md`, "Password rule"). Users notice it; where the project's fields disagree today, the proposal is the strictest of them;
- deletions another system may depend on;
- data migrations on big tables;
- each major upgrade above the floor: do it or pin it;
- reviewers and the merge rule, if not readable.

**This is the one round of questions of the program.** Before you send it, read the part of `05-program.md` D2 of every step, and list what each will need from the owner: type and stored-data questions of steps 5 to 8 (a value that changes what is stored, a deletion, a default), the exceptions each step would otherwise discover, the behavior changes, the deploy-bound steps. A question that is first found in step 5 and blocks it for days is a failure of this step: it goes in the kit's lessons, and the next run asks it here.

Ask each as a real question: what is going on in plain words, the options, what each means, the kit's reasons where a kit rule is involved, your proposal. One subject per question. An empty answer or "ok" means the proposal stands.

**One fact goes with the questions, with its numbers and without a question mark: what the pipeline of step 2 costs.** Every pull request and every push to the main branch runs the checks, tests and smoke runs of `04-guards.md` C4, and once a day the scheduled job runs the full dashboard and the mutation tool over the whole tree. Give the minutes per pull request and per day, from the step durations of 1.4 and a timed mutation run on the sample of 1.7 scaled to the tree, and the price from the profile.

Exit of step 1: `05-program.md` D2.
