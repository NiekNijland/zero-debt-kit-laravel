# Review rubric

What a reviewer, human or agent, checks in every pull request. The one-pattern table in the root `AGENTS.md` is the reference: when the PR does a task differently from that table, it is a finding, even when CI is green.

CI enforces most rows of that table (second section). A reviewer spends the time on what CI cannot see: the rows marked "review only", the findings below, and whether a guard that was supposed to keep a pattern is in the PR.

## Findings

Blockers

1. The PR adds an entry to an allowlist, baseline, suppression, skip, `ignoring()`, Rector skip, knip ignore, a `config.audit.ignore` or a reasoned-exception list inside an architecture test. These lists only shrink.
2. The PR converges a pattern without the guard that keeps it, removes a guard, makes a check non-blocking, scopes it to changed files, or hides its failure (`|| true`, `continue-on-error`). Every diff to `scripts/ci-ratchets.sh` outside adding a row, to the other scripts in `scripts/`, and to the pipeline files is read line by line.
3. Object-level authorization: an id from the request reaches a record without route binding and a policy; `Rule::exists` or `Rule::unique` on a table name; `DB::table`; `withoutGlobalScope(TenantScope::class)` on tenant data.
4. A moved or renamed event, notification, job, mail or morph type outside the three releases, or a changed or removed line in `tests/Support/Architecture/stored-class-names` without its alias (the guard fails that) or its data migration for `events.name`, `notifications.type`, queued payloads and morph columns (the guard cannot see that: you check it).
5. A migration builds an index or foreign key on an existing table without `CONCURRENTLY` or `NOT VALID`.
6. A commit with `[ratchet: <name>] <reason>` that raises a counter: read the reason and the diff. The tag is for a rule that moved, not for a violation that was easier to keep. On `test-doubles` and `event-fake-all` the reason says why the real class or a fake in `tests/Support` does not work. A raised cap in `scripts/debt-caps.json` needs `[ratchet: dashboard-cap-headroom]`: new code without its test is not a reason.
7. Production behaves differently after the PR and the description does not say so under "Behavior that changes on purpose".
8. A raised cap; a test that switches off middleware or exception handling; a new double of a class of the project where the real class or a fake works.

Warnings

9. New code does a task differently from the one-pattern table, in a part no guard decides.
10. Authorization by role name, or a permission check in a form request or controller that belongs on the route.
11. New behavior that no test names, or a test named after a class that does not exercise it or cannot fail. CI only checks that every Action, service, job, listener, command, policy, middleware, rule, mail, notification and cast has a `<Class>Test.php` and that a hook, a file under `hooks`, `utils` or `lib` and a component has a test next to it, the last three up to their cap (`scripts/debt-dashboard.sh`, `scripts/debt-caps.json`); whether the test is worth its name is yours to judge.
12. The PR changes a counter (a ratchet row, a file over 400 lines, an outdated package) and the description does not say which and by how much. `bash scripts/debt-dashboard.sh --offline` prints them.
13. A new or changed guard without a failing example for each other spelling of its construct: through a parent class or a sub-interface, as a function and as a method, under an alias or a full name.
14. A sentence in a guide that says a guard fails on something the guard's failing examples do not show; a sentence that describes a transition; a finished plan that is not archived.
15. A commit type that does not fit the change. On a PR that only touches docs or `*.md`, CI skips the commit message check: read the commit titles yourself, ticket and type.
16. Copy and UI: a string missing in one of the supported languages, wording the glossary in `AGENTS.md` rules out, a flash that carries data instead of one translated message; a change to what people see without a link to its design, or a difference between the screen and its design that the description does not name with its reason and that the PR did not carry back into `DESIGN.md`; markup for a job that has a component in the table under "Use what exists" in `DESIGN.md`, or a new look without a `cva` variant; a new variant or state without a story (`stories-coverage.test.ts` only catches a folder without any story).

Judgements (review only: no guard can decide these)

17. Frontend design: state with phases is a pure reducer over a tagged union with its own test; a context is split by what changes together.
18. A test exercises the class it names, and the class it names is the one whose behavior it asserts.
19. A value that depends on runtime configuration fits: the `$timeout` of a queued class stays under its supervisor's timeout in `config/horizon.php`; the lock of a scheduled task expires at most at its interval.
20. A migration's `down()` really undoes its `up()`; a type change on a big table has its migration plan in the PR.
21. The empty value of a checked helper (`Json::decodeOrNull()`, `Scalar`) is handled by its caller; an overriding property carries the same intent as an overriding method.
22. The reason of a `[ratchet: comments]` override meets the comment rule: a future agent would act differently because of the comment.

Suggestions

23. A comment that restates the code; mutable date casts; a new abstraction where deleting code would do.

## What CI already enforces, and what is review only

Every row of the one-pattern table in `AGENTS.md`, in the same order. A row without a guard is review only: check it by eye.

| Row | Guard | Review only |
| --- | --- | --- |
| Action (business logic) | `ActionNamingArchitectureTest`, `FinalReadonlyArchitectureTest`; `ArchClassShapeTest`: actions have `handle` | One public `handle()` |
| Banned call | PHPStan with the disallowed-calls rules in `phpstan.neon` | Whether a new `allowIn` has a reason |
| Broadcast channel | `BroadcastChannelArchitectureTest`: every channel an event sends on is registered, and every registered channel has its `<Prefix>ChannelTest.php` | The authorization closure of the channel |
| Catch an exception | `EmptyCatchArchitectureTest` | That the named exception is the one expected |
| Config | `ConfigAccessArchitectureTest`: config is read through the typed accessors |  |
| Container | `ContainerCallArchitectureTest`, `ContainerBindingArchitectureTest`: dependencies come through the constructor or `handle()`; a class of the application binds itself with an attribute |  |
| Controller | `ControllerNameArchitectureTest`, `ControllerAuthorizationArchitectureTest`, `ThinControllersArchitectureTest` | The check is on the route, not in the controller |
| Current time | PHPStan bans `Carbon\Carbon`, `time()`, `CarbonImmutable::now()` and `Date::now()` |  |
| Data for the frontend | `FrontendDataNamingArchitectureTest`; `ArchClassShapeTest`: the `Data` suffix; `TypeScriptAttributeArchitectureTest`: a TypeScript type named after its class; `TestTierArchitectureTest`: a model only inside `from*` | camelCase keys of an array a controller builds |
| Dispatch an event | PHPStan bans `event()`; `EventDispatchArchitectureTest`; `AuditEventTest`: an audit event implements `ShouldDispatchAfterCommit` | An event dispatched inside a transaction that is not an audit event |
| Enum | `StringBackedEnumArchitectureTest`, `EnumCaseArchitectureTest` | Whether a case value is readable where it is stored or sent |
| Existence and uniqueness rules | `DatabaseRuleArchitectureTest` with `DatabaseRuleGuard` (model class, no string form, `tenant_id` in the chain of a scoped model; no list) | A closure that only mentions `tenant_id` in an `orWhere` |
| File size | Backend: `FileSizeTest`. Frontend: ESLint `max-lines` | How a class is split |
| Flash message | `FlashMessageArchitectureTest` (key, one translated line); ESLint `toasts/no-success-toast` |  |
| Foreign key and index | `ForeignKeyIndexTest`, `TenantForeignKeyTest`, `ConcurrentIndexMigrationTest` |  |
| JSON (backend) | PHPStan bans `json_decode()` and `json_encode()` without `JSON_THROW_ON_ERROR` | Whether the `...OrNull()` helpers are used only where a failure is expected |
| JSON endpoint | `JsonEndpointArchitectureTest`, `BareDataReturnArchitectureTest` | The wrapper and status |
| Layout | `LayoutTest` with `LayoutGuard`; `OneModulePerDomainArchitectureTest` |  |
| Lock | `TenantRowLockArchitectureTest`: only the lock classes take a lock | Whether the expiry fits the work |
| Log line | `LogMessageArchitectureTest`, `ReportNotLogArchitectureTest`; `ArchClassShapeTest`: Sentry's facade only in the provider | The domain exception wraps the caught `Throwable` |
| Middleware around the next handler |  | Each such middleware has a test with a real request to a route that throws |
| Migration | `MigrationClassArchitectureTest`, `MigrationShapeArchitectureTest`, `ColumnTypeTest` | Whether `down()` really undoes `up()`; whether cascade, null or restrict is the right choice |
| Model | `ModelConventionArchitectureTest`, `ModelMagicStaticCallArchitectureTest`, `DateCastArchitectureTest`, `NoObserversArchitectureTest`, `NoRawTableArchitectureTest` |  |
| Narrow a type (backend) | PHPStan at level 10; `NoInlineVarCastArchitectureTest` |  |
| Per-tenant runtime state | `PerTenantStateArchitectureTest` |  |
| Queued class | `QueueNameArchitectureTest`, `QueuedClassLimitsArchitectureTest`, `UniqueJobArchitectureTest` | Whether the `$timeout` stays under the supervisor timeout of its queue; whether `uniqueId()` returns the right key |
| Rate limit | `RouteRateLimitCoverageTest` (no allowlist), `RateLimiterArchitectureTest` | The numbers in `config/rate_limiting.php` |
| Registry and its implementers | `RegistryArchitectureTest` |  |
| Route | PHPStan bans `Route::resource`, `apiResource`, `singleton`, `any` and `match`; `RouteParameterArchitectureTest`, `RedirectArchitectureTest`, `ImportSpellingArchitectureTest` | The props the controller sends match the props of the page |
| Route permission | `RoutePermissionCoverageTest` (no allowlist), `ObjectLevelAccessTest`, `RoleNameArchitectureTest`, `RoleAssignmentArchitectureTest` | The rule in the policy itself |
| Rows of another shop | `TenantScopeBypassArchitectureTest`: the tenant scope is bypassed in one trait |  |
| Scheduled task | `ScheduledOneServerArchitectureTest`, `ScheduledTaskArchitectureTest` | Whether the expiry fits the interval and the longest honest run of the task |
| Search typed text | `LikeSearchArchitectureTest` | A case-sensitive `'like'` with a variable pattern |
| Soft-deleted rows | `SoftDeletePruningTest` | Whether a model that stays unpruned is really still read |
| Stored class name | `StoredClassNameArchitectureTest`, `MorphMapTest` | A changed or removed line comes with its data migration or its alias (finding 4) |
| Test | `BannedTestCallArchitectureTest`, `NamedRouteUrlArchitectureTest`, `TestTierArchitectureTest`, `TestHelperMirrorArchitectureTest`, `TestAssertionArchitectureTest`, `TestFolderMirrorsAppArchitectureTest`, `NoSharedTestFixturesArchitectureTest`; ratchets `test-doubles` and `event-fake-all` | The lowest honest tier; whether the assertion can fail |
| Transaction | `ManualTransactionArchitectureTest` |  |
| UI string | `FrontendTranslationKeysTest`, `CopyWordingArchitectureTest` | The wording in each language |
| Validation | `FormRequestArchitectureTest`, `ValidatedInputArchitectureTest`, `RequestValidationArchitectureTest` | An `authorize()` kept with `#[Override]` really switches off a check of a parent request |
| Frontend | ESLint on the whole tree with `--max-warnings 0`, the settled rule groups in `eslint.config.js`; `npm run types`; ratchets `eslint-disable`, `ts-suppressions`, `vitest-skip` | Whether a test asserts the behavior it names |

House rules without a row:

| Rule | Guard | Review only |
| --- | --- | --- |
| Commit message starts with the ticket and a type | `scripts/ci-commit-messages.sh` (`PROJ-123 type: ...`, the type from its list) | Docs-only PRs skip it; whether the type fits the change |
| Dependencies free of known advisories | `npm audit --omit=dev --audit-level=low`, `composer audit --locked --abandoned=fail` (pull request pipelines) | Dev dependencies are covered by `npm audit` run by hand |
| Drift | `scripts/debt-dashboard.sh --offline` in the PHP checks step: every ratchet row, files over 400 lines, behavior classes without their own test, and support classes, frontend logic and components without a test against their caps in `scripts/debt-caps.json`; ratchet `dashboard-cap-headroom`: a cap only goes down | The rows that need a registry (advisories, packages behind): run it without `--offline` before a release |
| Comments | ratchet row `comments` | Whether a comment let through with `[ratchet: comments]` earns its place |
| Formatting and manifest | `vendor/bin/pint --test` and `npx prettier --check` on the whole tree; `composer validate --strict` | |
| Path in a guide | `DocumentedPathArchitectureTest`: every backticked path in an `AGENTS.md`, `DESIGN.md` and this rubric exists | Whether the named file still shows the rule it is the example of |
| Method length, traits, reflection in tests | `MethodSizeArchitectureTest`, `NoSingleUseTraitsArchitectureTest`, `NoReflectionInTestsArchitectureTest`, `NoGlobalImportInTestFilesArchitectureTest` | |
| Debug and exit | PHPStan `disallowedFunctionCalls`, `ExitArchitectureTest` | |

## PR checklist

The checklist lives in `.github/pull_request_template.md`. The host fills a new PR's description from that file when the PR is created in the web UI. PRs opened through the API may not get it, so agents copy the file into the description themselves. The UI lines in the checklist apply to every PR that changes what people see.
