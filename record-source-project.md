# Record: the lessons behind the kit

Optional reading. Each line is a lesson from the runs of this program so far (three Laravel projects, all on Bitbucket) and the place where it became an instruction. A run needs none of it: every instruction is in the numbered files and the catalogs. The file describes no project: it holds no profiles and only the few measured figures that a lesson needs.

Where the reference falls short of the kit binds a run, so that list is not in this file: it is the starter's `KIT-FINDINGS.md` and `reference-project/README.md`, which `04-guards.md` C1 tells you to read before copying.

## Declaring zero, and measuring

| Lesson | Now an instruction in |
| --- | --- |
| "Zero" was declared before an audit; much of the work followed the audit. | `02-step-1-discover.md` 1.8; `05-program.md` D4; `06-lock-and-audit.md` E2 |
| An old way keeps growing while it is being burned down, unless a ratchet holds it from the first day. | `04-guards.md` C5 |
| A counter that is redefined during the run cannot be compared with its own baseline. | `01-principles.md` rule 8 |
| A count can be wrong by a large factor when the counting tool does not support a construct of the pattern. | `04-guards.md` C6 |
| A counter can reach zero because the banned call moved behind a one-line wrapper; a ratchet row can be deleted or its pattern changed without a failure; a pattern can miss a spelling of what it counts. | `09-reviewer-brief.md`, auditor item 12; `04-guards.md` C2 and C6 |
| Uncommitted work in the owner's checkout, ignored generated files and a config merge that switches off lint rules all distort the first measurement. | `02-step-1-discover.md` 1.2, 1.4 and 1.6 |
| A coverage gate and frontend coverage that arrive last protect nothing during the run. | `05-program.md` D2, step 2 |
| Every honest step audit adds counters; without a rule for that the program stops after every step. | `05-program.md` D4 |

## Order of work and pull requests

| Lesson | Now an instruction in |
| --- | --- |
| Tests that are written first, then given new fixtures, then split are reworked three times. | `05-program.md` D2, steps 4 and 5 |
| Size moved into single-use traits is hidden, not removed. | `05-program.md` D2, step 7; `08-builder-rules.md` |
| A plan cut into many small pull requests is re-cut several times, and the small pull requests conflict with each other. | `05-program.md` D3 |
| A pull request built on an unmerged one inherits its review. | `07-process.md` section 9 |
| A bot that commits formatting conflicts with the pull requests it formats. | `04-guards.md` C4 |
| A lint check on changed files only hides the findings of the whole tree. | `04-guards.md` C4 |
| CI rounds are burned on what a local run would show. | `02-step-1-discover.md` 1.1; `04-guards.md` C4 |
| When no local test run is allowed, the first pipeline run is the first test run. | `04-guards.md` C1, "Proof without a test run" |
| A red step is not always the code: a runner can fail to pull an image, and retargeting a pull request starts another run. | `07-process.md` section 11 |
| Baselines taken on the main branch break a long-lived second branch; typed lint with a content cache hides violations; a guard can skip files under a dot directory. | `04-guards.md` C1, C4 and C5 |

## Rules and their guards

| Lesson | Now an instruction in |
| --- | --- |
| A rule that is only written down, or only on a dashboard nobody has to pass, breaks soon after "zero"; every guarded rule holds. | `01-principles.md` rule 2; `04-guards.md` C3, C6 and C7; `06-lock-and-audit.md` E3 |
| A rule left to review does not converge: existence rules on table names only reached zero with a guard. | `catalog/backend-laravel.md`, "Existence and uniqueness rules"; the sweep in step 8 |
| A guard has a blind side until a review looks for it: a contract reached through a sub-contract, the function form next to the method form of a call. | `04-guards.md` C1 |
| A guide must not promise a check the guard cannot make. | `04-guards.md` C1; `03-conventions.md` B2 |
| A rule the code breaks in many places needs a decision per site before it can have a guard. | `04-guards.md` C7, point 5 |
| A test file without an opening tag is printed, not run. | `catalog/tests-database-ci-docs.md`, "Every test file asserts" |
| A call that looks like an assertion and is not one lets a test pass on anything. | the break sample in `09-reviewer-brief.md` |
| The dead-code rule does not see a method that a test calls on an untyped value. | `05-program.md` D7 and step 9 |
| Closing classes fails on forms a text search does not see: a subclass built from a string, a double of a class named in config, a facade over a class that became final, a fallback double in a generic helper. | `05-program.md` D8 |
| A cold review can "confirm" what the pipeline disproves. | `09-reviewer-brief.md`, "Say how you know" |

## Runtime

| Lesson | Now an instruction in |
| --- | --- |
| A test with exception handling off passes on a middleware that commits its transaction on failure. | `10-hazards.md`, "A test with exception handling off" |
| Correcting a misspelled setting key changes which value is read. | `08-builder-rules.md`, "Code rules" |
| Green CI and every counter at target can still let a server error through: a real cache store can refuse what the in-memory one returned unchanged, and a deploy can fail while every pull request is green. | `05-program.md` D4, point 5; `07-process.md` section 2, point 10 |
| A runtime check that is "the owner, by hand" is not repeated. | the smoke suite and `05-program.md` D4 |

## Working with agents

| Lesson | Now an instruction in |
| --- | --- |
| A formatter run on a file list from a shared worktree touches another builder's files; scratch scripts in one folder overwrite each other. | `08-builder-rules.md`, "In a shared worktree" |
| A helper class moved one folder up keeps its parent-directory depth and breaks the tests that use it. | `08-builder-rules.md`, "Moving or renaming classes" |
| A builder runs out of context on a long list of classes; a polling loop can hang; a codemod can delete comments that hold real traps. | `07-process.md` sections 7 and 8; `08-builder-rules.md`, "Scripted changes" |

## Programme shape and ownership

| Lesson | Now an instruction in |
| --- | --- |
| A formatter config that the organisation hosts, and a pipeline autofix commit, were replaced by a rule of the kit before anyone asked; both were undone on an open PR. A measured "nothing changes" hid that it was a governance question. | `01-principles.md`, "Who decides what"; `02-step-1-discover.md` 1.1; `templates/tool-configs.md`; `04-guards.md` C4 |
| The base branch was assumed from the deploy pipeline, and the whole stack had to merge the branch the team really works on. | `02-step-1-discover.md` 1.1 |
| Six stacked guard PRs read and rewrote the same guide rows: every merge conflicted the next in meaning, every push voided the approvals, and one merge landed on a stack parent and never reached the main line. | `05-program.md` D2 step 3 and D3; `07-process.md` section 9; `03-conventions.md` B2 (one rows file per guard) |
| Adding the kit took long, mostly in waiting and repair: slow pipelines, late owner questions, a secret-shaped test string that forced a new branch, test databases shared by agents, usage limits hit together. | `04-guards.md` C4 ("CI speed"); `02-step-1-discover.md` 1.10; `08-builder-rules.md`; `07-process.md` section 7 |
| The same blind sides came back in every review of a guard. | `04-guards.md` C1, "The standing blind-side list"; `reference-project/files-to-copy.md`, "Guards" |
| A time-boxed exception for an advisory needs its own rule: dated, short, never renewed, for advisories only. | `04-guards.md` C3; `tools/README.md`, "`audit-gate.sh`" |
| Measured in one pipeline: one step running thirteen checks made each tool two to three times slower than alone; result caches took a format check from 131 s to 4 s; worker counts could not be told apart in the noise. | `04-guards.md` C4 ("What the measurements said"); `ci/checks.sh` (`PARTS`); `ci/prepush.sh` |
| A chain queued before a class move died after it: a serialized closure names its defining class and every class of its body, and only a run that dispatches on the old tree and processes on the new sees it. | `05-program.md` D4 point 5 and D9; `10-hazards.md`, "Stored names"; `04-guards.md` C1 (register row) |
| The move tool was one fifth rename rules; five phases, a return mode and a map-driven TypeScript tool were what moved a large tree of classes. | `05-program.md` D9 ("The move tool"); `tools/move-ts.mjs`; `tools/map-normalise.sh` |
| An uncounted flake class (ties in an ordering, unseeded random values, timer-dependent delays) and a prevent flag with no fake stayed in the base until a sweep found them. | `05-program.md` step 2 and step 4; `catalog/backend-laravel.md` ("Ordering a read"); `catalog/tests-database-ci-docs.md` ("Hermetic base set-up") |
