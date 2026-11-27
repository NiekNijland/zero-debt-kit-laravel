# Files to copy from the starter

Paths are in <https://github.com/NiekNijland/zero-debt-laravel>, read at commit `5310ea7` (pinned to kit commit `e48979c`). Order of use: the shared reader first (`04-guards.md` C1), then the documents, then the rest as the program reaches it. A path that a later starter commit moved is the starter's to fix; open the file in the starter and compare with the kit before copying (the kit wins).

## The shared reader and the test support

| Path | For |
| --- | --- |
| `tests/Support/Architecture/PhpSource.php`, `PhpFileInspector.php`, `PhpCallReader.php`, `ClassGraph.php`, `TypedReceivers.php`, `FolderScan.php`, `TemporarySource.php`, `YamlNode.php` | the one parser and the folder, call and declaration readers every guard depends on |
| `tests/Support/SourceSandbox.php`, `Globs.php`, `RecursiveFiles.php`, `ClassNames.php`, `Narrow.php`, `Users.php`, `ApplicationRoutes.php` | sandbox trees for guard examples, glob and path helpers, the checked narrowing of `mixed` |
| `tests/Support/PHPStan/` | analyser extensions for Pest (`$this` in closures, `arch()`, higher-order calls) and the dead-code provider for by-name framework calls |
| `tests/Pest.php`, `tests/TestCase.php`, `phpunit.xml`, `tests/Feature/HermeticBaseTest.php` | the hermetic base test, the groups, the database pinned to a host and a port |

## Guards (detector class in `tests/Support/Architecture/`, thin test in `tests/Unit/<Name>ArchitectureTest.php`)

| Area | Guards |
| --- | --- |
| Shape of the guards and the guides | `GuardShapeGuard`, `GuardGroupGuard`, `GuideRulesGuard`, `OnePatternRubricGuard`, `DocumentedPathGuard`, `PestExampleReader`, `PestBindingReader` |
| Classes | `ClosedClassGuard`, `ControllerNameGuard`, `ThinControllerGuard`, `MethodSizeGuard`, `FileSizeGuard`, `ModuleLayoutGuard`, `ModuleOverlapGuard`, `EnumCaseGuard`, `StringBackedEnumGuard`, `SingleUseTraitGuard`, `ModelConventionGuard`, `ModelMagicStaticCallGuard`, `DateCastGuard`, `ImportSpellingGuard`, `GlobalImportGuard` |
| Calls and state | `ConfigAccessGuard`, `ContainerCallGuard`, `ContainerBindingGuard`, `EventDispatchGuard`, `ManualTransactionGuard`, `ReportNotLogGuard`, `LogMessageGuard`, `EmptyCatchGuard`, `ExitGuard`, `InlineVarCastGuard`, `LikeSearchGuard`, `RawTableGuard`, `EloquentBuilderGuard`, `DatabaseRuleGuard`, `HttpRefusalGuard`, `RedirectGuard`, `RouteFileLoadGuard`, `RouteParameterGuard`, `NamedRouteUrlGuard` |
| Requests and responses | `FormRequestGuard`, `RequestValidationGuard`, `ValidatedInputGuard`, `JsonEndpointGuard`, `BareDataReturnGuard`, `ControllerAuthorizationGuard`, `RoleNameGuard` |
| Queues, schedule, registration | `UniqueJobGuard`, `QueueNameGuard`, `QueuedClassLimitsGuard`, `RateLimiterGuard`, `ScheduledTaskGuard`, `ScheduledOneServerGuard`, `BootstrapRegistrationGuard` |
| Migrations | `MigrationShapeGuard`, `MigrationClassGuard`, `ConcurrentIndexGuard` |
| Tests | `TestClassGuard`, `TestTierGuard`, `TestLocationGuard`, `TestHelperFunctionGuard`, `TestHelperMirrorGuard`, `TestAssertionGuard`, `TestCaseFixtureGuard`, `BannedTestCallGuard`, `ReflectionInTestsGuard` |
| CI and repository files | `CiSafetyGuard`, `GithubWorkflowGuard` with the ten classes it needs in `tests/Support/Architecture/` (`GithubWorkflowReader`, `GithubWorkflowRules`, `GithubJobRules`, `GithubRunnerRules`, `GithubChecksRules`, `ChecksParts`, `DatabaseEngine`, `AuxiliarySecretRule`, `YamlNode`, `PhpSource`; the starter's README names them and a test holds that they read no other class of the test support) and the filled skeletons in `tests/Support/Ci/` (`CiExamples.php`, `CiHostExamples.php`, `CiHostVariants.php`, `CiRunnerExamples.php`), `CodeOwnersGuard`, `AnalyserPathsGuard`, `ComposerScriptsGuard`, `BlameRevisionsGuard`, with `docs/guards/ci-contract.md` and `tests/Unit/CiContractArchitectureTest.php` |

The Pest `arch()` rules are `tests/Unit/Arch*Test.php`, `ActionNamingArchitectureTest.php` and `FinalReadonlyArchitectureTest.php`. Coverage tests that ask the booted application: `tests/Feature/Http/RoutePermissionCoverageTest.php`, `RouteRateLimitCoverageTest.php`, `ObjectLevelAccessTest.php`, `tests/Feature/Database/ColumnTypeTest.php`, `ForeignKeyIndexTest.php`, `MigrationShapeTest.php`, `tests/Feature/Lang/TranslationKeysTest.php`, `tests/Feature/Providers/` (strictness and rate limits).

## Configs and scripts

| Path | For |
| --- | --- |
| `phpstan.neon`, `phpstan-tests.neon` | the analyser at level 10 with the switches it keeps (the ratchet row `analyser-switches` counts them; the starter has no `tsconfig.json`, so the compiler options of the kit's count are absent) and the banned-call rules; the test config with the stub folders |
| `rector.php`, `pint.json` | the refactoring sets and the strictness rules of the formatter |
| `composer.json` | the six composer scripts, the advisory block, the manifest floor |
| `scripts/checks.sh`, `kit-config.sh`, `ci-ratchets.sh`, `debt-dashboard.sh`, `debt-caps.json`, `keyed-baseline.sh`, `audit-gate.sh`, `audit-exceptions.json`, `ci-commit-messages.sh`, `ci-parallel.sh`, `prepush.sh`, `init.sh`, `coverage-floor.txt`, `mutation.sh`, `mutation-floor.txt` | the kit's scripts with the project's config block; never copy them from here when `tools/` has the newer body: run `tools/doctor.sh` |
| `.github/workflows/kit-checks.yml`, `kit-sync.yml`, `kit-canary.yml`, `.github/CODEOWNERS`, `.github/pull_request_template.md`, `.zero-debt-kit-version`, `docs/kit-sync.md`, `renovate.json` | the pipeline in its runner form, the sync and the canary, the code owners, the pin |

## Documents

| Path | For |
| --- | --- |
| `AGENTS.md`, `CLAUDE.md`, `app/AGENTS.md`, `tests/AGENTS.md` | the glossary, the source order, the one-pattern table, the folder table, the test rules |
| `docs/review-rubric.md`, `docs/decisions/0001-one-backend-pattern.md`, `docs/ci.md`, `docs/guards/ci-contract.md`, `KIT-FINDINGS.md` | the review rubric, a decision record, what each CI part does, a guard-rows file, where the starter found the kit wrong |

## Example files with the one pattern to copy

`app/Modules/Notes/Actions/CreateNote.php` (action), `app/Http/Controllers/Api/Note/StoreController.php` (controller), `app/Modules/Notes/Data/NoteData.php` (JSON response), `app/Models/Note.php` (model), `app/Modules/Notes/Enums/NotePermission.php` (enum), `app/Providers/RateLimitServiceProvider.php` (rate limit), `app/Support/Translate.php` (narrowing a type), `database/migrations/*_create_notes_table.php` (migration).
