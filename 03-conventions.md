# Conventions: the one way, written down

Used twice. In step 1 you use B1, B3 and B4 to map the catalog onto the project (`02-step-1-discover.md` 1.5). In step 3 you write the documents of B2.

Output of step 3: the documents (the `AGENTS.md` set, a decision record and the review rubric) and the guards they name, each guard with its counted list, on one integration branch and in one PR (`05-program.md` D2, step 3). No source file moves.

## B1. The way per task

For each task the kit's catalog decides (`catalog/`; who decides what is in `01-principles.md`). Code that does it another way is a backlog row and a counter.

Only for a task the catalog does not cover, pick one way with this order of preference:

1. What the stock framework documentation does.
2. What the library the project already uses calls idiomatic.
3. What the newest, cleanest code in the project already does.
4. The way with less code. Flat beats layered. No new abstraction, strategy object or base class to make two small things share code.

A way chosen like this is written back into the kit's catalog when the run closes (`06-lock-and-audit.md` E4), so the next project gets the same answer.

Limits on a chosen way:

- It is **guardable**: prefer the variant a lint rule, analyser rule or architecture test can check without a list of exceptions.
- It has a **real example file** in the codebase that passes every other rule. If none exists, the first PR of that pattern creates it.
- Explicit intent stays (immutable date casts, typed config reads): do not trade it for a shorter default.
- When a written plan of the project already settles something (a planned migration to another library), the plan wins: do not polish code that plan deletes.
- Three near-identical flows stay three when sharing them needs machinery. Close the item and say why.

## B2. The documents

Create or update, each from its template in `templates/repo-docs.md`:

| File | Content |
| --- | --- |
| Root `AGENTS.md` | Product context and glossary (if the project has them), house rules, and the **one-pattern table**: concern, the single way, the file to copy. Ends with the two guard rules |
| Backend `AGENTS.md` | The layout: which folder holds what and how a class in it is named; the pure layers; "one way per task" paragraphs; what moving a class does to stored names |
| Frontend `AGENTS.md` | Layers and import direction, file names, component shape, state, data fetching, forms, the doors to browser APIs |
| Tests `AGENTS.md` | Test tiers (which set-up each tier may use), where a test lives, helpers, fixtures, fakes, the one way for config, clock and container in tests, the coverage statement that matches the real threshold |
| Decision record (`docs/decisions/NNNN-one-<area>-pattern.md`) | Context, decision, consequences |
| Review rubric (`docs/review-rubric.md`) | Numbered findings with severity, and the table "what CI enforces, what is review only" |
| Guard rows (`docs/guards/<guard>.md`) | One file per guard, holding the guide rows and tables that guard reads (the words it pins, the layout or verb table, its counted-list rule). The one-pattern table and the rubric name the guard by its name from the list below and link the file. Two guards never edit the same lines |

**Guard names** are chosen once, in the documents PR, as a short list in the root `AGENTS.md` (for example layout, class shape, entry points, banned calls, spelling, size, tests, schema, register, documents). A row names its guard by that name and no other; the guard over the guides fails a name that is not on the list. When two guard branches are built at the same time, the second to land resolves any shared row by hand, row by row, and then runs the whole guard group.

The PR template of the host exists since step 2 (`05-program.md` D2); step 3 adds the lines that need the patterns.

Writing rules for these files: short sentences, present tense, the final state (never "will", never "for now"). A rule names its guard. A sentence says "the guard fails" only for what the guard decides; what it cannot decide (a value that depends on runtime configuration, a judgement) is written as "review checks that, the guard does not". Every path in backticks names a file or folder that exists, and an example path uses a placeholder such as `<Name>`; a guard over the guides checks that (`catalog/tests-database-ci-docs.md`, "Docs"). "Lands with <PR>" is allowed in the Copy column of the one-pattern table only, for an example file of step 8, until that PR merges; it then gets the path. Anywhere else (a guide sentence, the Guard column of the rubric) it fails the guard over the guides: build the guard when an existing reader decides the rule, or write "review checks that, the guard does not (rubric item N)" with the reason it is not mechanical. Write the guide sentence last, after the guard exists, so no sentence outlives its guard. Where the repository already has scoped `AGENTS.md` files, extend them; never fork a second file for the same area.

Further rules for the sentences a guard reads:

- **Split each sentence into the mechanical part and the judgement**: the part a parser decides goes to the guard, the rest to the rubric, so the guard author does not change the guide. A rule about something that does not exist yet (a queue enum nobody has made) is built on examples and listed in its PR as "not exercised by the real tree".
- **A rule names a notion the code carries.** "One owner class per kind of temporary file" has none; rewrite it into one a parser sees ("a binary is run by one class") or mark it review-only with its rubric number. Where a rule has two readings (a test location rule about classes and about flows), the sentence states the reading the guard checks. A layout table that uses one folder name for two layers says so in its row.
- **A guide never quotes a banned call literally**, and no ratchet row counts a guide: the rows exclude `*.md`. A guide that must show the call splits it (`Event::` then `fake()` in two spans) or names it.
- **Run the project's formatter over a new guide** before committing it, or keep guides out of the formatter's paths: a markdown guide in a frontend folder fails the frontend format check.
- **Every layer statement of the team's own rule file** is a sentence of the guides with its guard, or is struck by a decision row; a guard is built from the guides only.
- **A guard batch name carries a topic** (`architecture: layers`, `architecture: data access`, `architecture: errors and logging`), so one PR can drop exactly its own sentences. A sentence that no reader in the tree can decide stays a rubric item, and the guide says which.
- **Run the layout guard over the example files** the one-pattern table names before the table is signed: a table cell can contradict its own example.

## B3. The layout

Where code lives is the most expensive thing to change, because every move conflicts with every open branch. The kit fixes it; step 1 only fills in the domain names.

- The framework layer stays where the framework puts it (controllers, requests, middleware, models, policies, providers).
- Domain code lives in one module per domain, under the kit's module root (`app/Modules/<Domain>` in a PHP project, `src/modules/<domain>` in a TypeScript project; another root is renamed in step 5), in the kit's fixed set of sub-folders with a naming rule per folder. The set, the meaning of each folder, the pure layers and the one sanctioned crossing are in `catalog/backend-laravel.md`, "Structure" (another stack: `catalog/other-stacks.md`, "Structure").
- **External-system adapters are their own layer next to the modules**: `app/Integrations/<System>` in a PHP project (`src/integrations/<system>` in TypeScript), one folder per external system (OCR, PII detection, embedding, LLM, search, storage, and so on), each with a contract, its providers and its fakes. An adapter holds no domain type and no domain rule; domain code reaches an adapter only through a service of a module, and may name the adapter's contracts, data classes and exceptions (the vocabulary that flows through the service), never its providers or support classes. Adapters are never folded into a module's `Services` folder and never appear in the move map (no move, no alias). The layout guard checks both directions: an adapter imports no module class, and a class outside `app/Providers` and the services of a module does not import an adapter. Decide the list of adapters in step 1 with the list of domains.
- Every project has the module layout. A project with one domain has one module.
- The layout is written as a table in the backend `AGENTS.md`. The layout guard is generated from that table.

What the project decides: the list of domains, and which of today's classes goes into which module and folder. Two people must sort the same class the same way from the folder meanings alone; where they would not, the meaning in the catalog is what needs sharpening, as a kit change (`06-lock-and-audit.md` E4).

## B4. Decisions

Use the decisions table in `zero-debt-plan.md`:

- one row per decision: id, decision, why, who decided (the owner, `owner (delegated)`, a named colleague, `lead, on request`);
- append-only; a changed decision is a new row that says which it replaces;
- a decision changes a guard in the PR that needs it, and is written in the relevant `AGENTS.md`.

**Settled by the kit.** These hold in every project and are not asked. A different answer is an owner exception with its reason. Four are stated here and nowhere else:

| Topic | The kit's answer |
| --- | --- |
| Old migrations: squash or keep | keep; make them independent of application classes; never exclude them from analysis |
| Test framework (PHP) | a technique: Pest or PHPUnit, the project's own, one per project and never mixed. A project that has both converges to the one the owner names in step 1. Every test pattern and guard exists in both forms (`04-guards.md` C1) |
| Model to DTO mapping | the library idiom, inside static `from*` factories |
| Commit message | `<TICKET> <type>[(scope)][!]: <subject>` with the type from the list `TYPES` in the config block of the commit-message check (`tools/README.md`); a project without a ticket system uses one fixed prefix, `NOTICKET` |

The rest is settled where it is written:

| Topic | Where |
| --- | --- |
| Analyser level; major upgrades; tool swaps | `05-program.md` D2, steps 2 and 9, and D7 |
| Limits; one huge data file; frontend strictness; one spelling per construct | the catalogs: "Structure", "Strictness", "Translation source", "One spelling per construct" |
| Dead features, flags on for everyone, test-only routes and methods | `05-program.md` D2, step 4; `catalog/tests-database-ci-docs.md`, "Dead code" |
| The coverage floor and the mutation score | `tools/README.md`, "Coverage" and "Mutation" |
| Class alias lifetime after a move; classes closed for extension | `05-program.md` D9 and D8 |
| A rule the code breaks in many places, each needing its own answer | `04-guards.md` C7 |
| Secret scan, smoke suite, one local command, object-level access sweep, mutation testing, contract fixtures | `02-step-1-discover.md` 1.7 |
| The scheduled job | `04-guards.md` C4 |
| Force push; a revert | never: `07-process.md` sections 10 and 5 |
| What may stay above zero | only the rows of `01-principles.md`, "Shrink-only rows"; any other row stays above zero only through the owner's approval of a decision row with an end date |

**Asked, never settled by the kit.** Where a formatter config comes from, whether the pipeline writes (an autofix commit) or only checks, and which shared images, deployer and merge-back bots stay are the organisation's: step 1 asks with the kit's reasons (`02-step-1-discover.md` 1.1) and the answer is a decision row. The kit's own answer (the config in the repository, check-only CI) is the default for a project that has none of its own.

**The project's own business.** Read from the repository or asked in step 1, then recorded in the profile: who reviews and what merges a PR, whether the lead may merge, who announces and where, freezes, who rolls back a release, how PRs merge (merge commit, squash or rebase), the retention period of soft-deleted rows, whether there is a second auth mechanism to remove (a behavior change: the owner's).

## B5. Questions

- Details the kit does not fix (names, error texts, test set-up, technical fill-in, a threshold the catalog leaves to the measurement) you decide and record with the reason.
- Product, scope, privacy, behavior that users or other systems notice, exceptions to a kit rule, and collisions between two principles go to the owner as real questions with a proposal.
- Planning, deadlines, estimates and priority are not asked and not written down.
- Never ask about something that does not exist: check the route, page or action first.
