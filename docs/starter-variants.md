# Starter variants: the backlog

The reference of this kit is the strict Laravel starter, <https://github.com/NiekNijland/zero-debt-laravel> (`reference-project/README.md`). It is one small application: a JSON API for notes with localized pages, on PostgreSQL, with no frontend, no tenants, no queues of its own and no Livewire. The kit prescribes more than the starter shows. This file lists what an earlier, anonymized and non-running extract of another project held that the starter does not, and what each taught, so a future variant of the starter can bring it back as code that runs. It is a backlog, not a plan: no order, no date. The descriptions of what a guard checked are read from the names and the old extract's README, which were not run: unverified.

A variant is built the same way as the starter: the guard comes with its application code, its thin test with failing examples, passing examples and a real-tree case, and the variant passes its own `scripts/checks.sh`. A guard that returns here lands in the starter (or a variant of it), not in this repository. Until then the catalog row says "no worked example in the starter".

## Multi-tenant variant

| Guard or test | What it checked |
| --- | --- |
| `TenantCreationGuard`, `TenantScopeBypassGuard`, `TenantRowLockGuard` | a tenant-owned row is created through the tenant-aware path; a scope is not bypassed (`withoutGlobalScopes`, raw table calls); a row lock names the tenant |
| `PerTenantStateGuard` | state that must be per tenant (cache keys, files, settings) does not sit in a global one |
| `UserRoleTableGuard`, `RoleAssignmentGuard` | roles are assigned through the one action; the pivot table is not written directly |
| `tests/Feature/Database/TenantForeignKeyTest.php` | a schema test: a foreign key between tenant-owned tables carries the tenant column |
| `DatabaseRuleGuard`, tenant half | a `unique` or `exists` rule on a tenant-owned table names the tenant scope (the starter has the non-tenant half) |
| object-level access, platform half | the route sweep of the coverage test over a map from model to a factory of a foreign record, across tenants (the starter has the single-owner form) |

## Frontend variant (Inertia and React)

| Guard or file | What it checked |
| --- | --- |
| `InertiaPageGuard`, `InertiaImportGuard` | a page is rendered through the one Inertia spelling; the frontend imports its framework from one module |
| `TypeScriptGeneratorGuard`, `TypeScriptAttributeGuard` | generated TypeScript types come from one generator and carry the one attribute |
| `FrontendDataNamingGuard`, `FlashMessageGuard`, `CopyWordingGuard` | a data class that reaches the frontend is named by its page; a flash message goes through the one helper; the copy a person reads is in the translation files |
| `tests/Support/FrontendTranslationKeys.php`, `FrontendTranslationLiterals.php`, `tests/Unit/Modules/Copy/Commands/FrontendTranslationKeysTest.php` | the translation-key test over the frontend sources |
| `scripts/ci-frontend-checks.sh`, `ci-frontend-lint.sh`, `ci-frontend-tests.sh` | the frontend parts of the checks script (typecheck, lint with the project's rules, tests with coverage) |
| `eslint.config.js`, `knip.json`, `tsconfig.json`, the frontend `AGENTS.md` files | still in `reference-project/examples/frontend/` as examples that do not run; a variant makes them run |

## CI hosts other than GitHub

| Guard | What it checked |
| --- | --- |
| `CiContractGuard` (Bitbucket form, with `ChecksParts`), `GitlabPipelineGuard`, `GithubWorkflowReader`, `GithubWorkflowRules`, `GithubRunnerRules`, `GithubChecksRules`, `DatabaseEngine` | the contract test of `04-guards.md` C4 for the Bitbucket and GitLab forms and for the runner form of GitHub; the starter has its own GitHub reader for the one workflow it uses |
| `tests/Support/Ci/CiExamples.php`, `CiHostExamples.php`, `CiHostVariants.php` | the filled skeletons the failing cases change one line of. `CiHostExamples.php` is kept as a proof fixture (`tools/proof/fixtures/ci/`): the proof scenarios `CX27`, `PF03` and `RG06` compare it with `ci/github-actions.yml` and `ci/github-actions-runner.yml` |

The `ci/` skeletons for Bitbucket and GitLab are proved by text checks only (`tools/proof/`); their readers have no running code in the starter.

## Other guards and tests the starter does not have

| Guard or test | What it checked |
| --- | --- |
| `StoredClassNameGuard`, `RegistryGuard`, `ObserverRegistrationGuard`, `EventWriteGuard`, `BroadcastChannelGuard` | stored class names in snapshots, registries read through the autoloader, observer registration, writes of events, broadcast channels; the register form is kept as an example in `reference-project/examples/php/` |
| `LayoutGuard`, `tests/Unit/LayoutTest.php` | the older, literal form of the module layout guard; the starter has `ModuleLayoutGuard` |
| `tests/Feature/Models/MorphMapTest.php`, `SoftDeletePruningTest.php`, `tests/Feature/Modules/Events/AuditEventTest.php`, `tests/Feature/Routing/RouteFilesTest.php` | the morph map is complete, soft-deleted rows are pruned, an audit event is written, route files load |
| `RunInTransaction` middleware, `Json`, `Scalar`, `StringKeyed` helpers | the transaction middleware (kept as an example) and the checked narrowing helpers for decoded JSON (the starter narrows through `Translate` and `tests/Support/Narrow.php`; a decode helper with the throwing flag is not there) |
| `MovedClassServiceProvider`, the `Queue` enum, the stored-name aliases | moved classes and queue names for an application with real queues |
| Livewire components, a browser smoke suite | rows of `catalog/backend-laravel.md` and `catalog/frontend-react.md` that no starter has yet |

## Done when

A variant is done when it runs: its guards pass their own examples, `bash scripts/checks.sh` is green, and its pin and canary (`.github/workflows/starter-canary.yml` in this repository, the variant's own `kit-canary.yml`) show a kit change that breaks it.
