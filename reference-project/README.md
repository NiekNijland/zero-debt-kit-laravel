# The reference: the starter, plus a few examples

The worked example of this kit is a running project, not an extract: the strict Laravel starter, <https://github.com/NiekNijland/zero-debt-laravel>. It is a Laravel 13 application (a JSON API for notes with localized pages) built at the end state of this kit: PHPStan level 10, a test suite with 100% line coverage, the guards of `04-guards.md` rewritten for a real application, `scripts/checks.sh` green, and a nightly canary that runs the next kit version against it. Everything in it runs; a fix in a guard is a fix that was run.

**Copy from the starter, not from this folder.** `files-to-copy.md` lists the starter paths a project copies and what each is for. Where the starter and the kit differ, the kit wins; the starter's `KIT-FINDINGS.md` lists the cases it found. The starter is pinned to a kit commit (`.zero-debt-kit-version`) and is therefore always a few steps behind the kit's `main`; `files-to-copy.md` names the commit its paths were read at.

When a kit file says "the reference", "the worked example" or gives a bare file name in a Guard column (`tests/Unit/` for a test, `tests/Support/Architecture/` for a detector class), it means the starter at that path. A name the starter does not have is either in `examples/` here or is listed in `docs/starter-variants.md` as a variant that does not exist yet.

## What is in this folder

Only what the starter does not cover and a reader can follow without an application. None of it runs here: each file is an example of a shape, marked as such, and a project adapts it to its own classes.

| Path | What it shows | Why it is here |
| --- | --- | --- |
| `examples/php/app/Http/Middleware/RunInTransaction.php` | the shape of the transaction middleware that `10-hazards.md` describes | the starter has no hazards of this kind |
| `examples/php/tests/Support/Architecture/CountedList.php`, `examples/php/tests/Unit/CountedListTest.php` | the counted list of `04-guards.md` C5: a committed file, `added()`, `stale()`, `violations()` | the starter keeps no counted list |
| `examples/php/tests/Support/Architecture/StoredNameRegisterGuard.php`, `examples/php/tests/Unit/StoredNameRegisterArchitectureTest.php`, `examples/php/database/stored-names/` | stored class names as register files compared with the code in both directions | the starter has no queue, event or notification classes that are stored by name |
| `examples/frontend/` | `eslint.config.js`, `knip.json`, `tsconfig.json` and the `AGENTS.md` of the frontend folders (React with Inertia) | the starter has no frontend; `catalog/frontend-react.md` points here |

The PHP examples need the shared reader of the starter (`PhpSource`, `PhpFileInspector`, `ClassGraph`, `PhpCallReader`) and its `SourceSandbox`; copy those from the starter first (`AuxiliarySecretRule` needs none of them). They were written for an earlier revision of the guard folder, were never run as a whole, and the prose in them is for the reader of the kit (a project deletes it: the ratchet row `comments` counts it).

## What is not here

The guards for a multi-tenant application, for the frontend (Inertia pages, generated TypeScript types, translation keys of the frontend), the CI guards for Bitbucket and GitLab, the guards for broadcast channels, flash messages, registries and moved classes, and the coverage tests for morph maps, soft-delete pruning and audit events were in the earlier extract and are not in the starter. They are listed with what each taught in `docs/starter-variants.md`, as the backlog of starter variants; the kit still prescribes each of them (`catalog/`, `04-guards.md`) and says how to build it.

The CI skeletons of the kit (`ci/`) are proved against example files that live with the proof, not here: `tools/proof/fixtures/ci/CiHostExamples.php`. The starter's own GitHub guard reads the one workflow the starter uses.

## Running the starter against the kit

`.github/workflows/starter-canary.yml` (a proposal: see its header) checks out the starter, puts the kit's current scripts into it and runs its checks. A kit change that breaks the starter is found there, not by the next project.


A Laravel skeleton's `.gitattributes` marks `/.github export-ignore`. A zip, `git archive` or `composer create-project` copy then lacks the workflows and `CODEOWNERS`; a project that uses its repository as a template drops that line (the starter did).
