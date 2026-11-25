# TESTING

PestPHP v5 with multi-tenant support. Every new Action, service, job, listener and command gets a test that names it; each has a `<Class>Test.php` in the folder that mirrors its own. `bash scripts/debt-dashboard.sh --offline` runs in the PHP checks step and fails a pull request with a class in an `Actions`, `Services`, `Jobs`, `Listeners` or `Commands` folder that no test file is named after (row `behavior-classes-without-own-test`) or that no test names at all (row `untested-behavior-classes`). Every policy, middleware, rule, mail, notification and cast has its own `<Class>Test.php` too: the row `support-classes-without-own-test` counts the ones without and fails above its cap in `scripts/debt-caps.json`, which only goes down (the ratchet row `dashboard-cap-headroom` fails a pull request that raises a cap). The coverage service's project status (`coverage-service.yml`, target `auto` with a small threshold) fails a pull request that lowers total coverage beyond it, and the patch status fails one whose changed PHP lines are covered clearly below that total. Frontend coverage is uploaded under its own flag and only informs, so new frontend code is held to its test by review and by the rubric.

## STRUCTURE

```
tests/
├── Unit/             # Tiers T0 and T0.5: no booted application, no database
├── Feature/          # Tiers T1 and T2: booted application, with tenant context
│   ├── Http/Controllers/  # app/Http/Controllers
│   ├── Modules/      # app/Modules
│   ├── Models/Tenant/     # Tenant-scoped model tests
│   ├── Services/          # Service integration tests
│   └── View/              # Blade views and app/View
├── Integration/      # Tier T2: framework + database (Modules, Jobs, Services)
├── Concerns/         # Tier traits and shared fixtures
├── Support/          # Helper classes (static helpers per test file and per theme, template database, architecture guards)
└── Fixtures/         # Files the tests read
    └── imports/       # product import files (valid, partial, empty)
```

## TIERS

Pick the lowest tier that is honest for the test. Directories bind the base class, traits opt a file into another tier.

| Tier | Where | Base | What it gets |
|------|-------|------|--------------|
| T0 | `tests/Unit` | `Tests\PureTestCase` | Plain PHPUnit. Resolving the container fails the test. |
| T0.5 | `tests/Unit` + `uses(Tests\Concerns\BootsBareContainer::class)` | `PureTestCase` | Container with cached config, facades, events, cache and Carbon. No providers, no database. |
| T1 | `tests/Feature` + `uses(Tests\Concerns\GuardsAgainstDatabaseAccess::class)` | `FeatureTestCase` | Booted application. Any query or transaction through Eloquent or the query builder throws and fails the test, even when the code under test swallows the exception. |
| T2 | `tests/Feature`, `tests/Integration` | `FeatureTestCase` | Booted application and the real database. |

Rules:

- `tests/Unit` never extends or uses a booting base (`Tests\TestCase`, `Tests\FeatureTestCase`, `RefreshDatabase` variants). `TestTierArchitectureTest` enforces this with an AST check that sees aliased imports, group imports, inline names and indirect inheritance through support traits.
- A test that needs the booted application but no database moves to `tests/Feature` and opts into T1.
- `Tests\TestCase` (the booted base) fakes `Sleep` in sync with Carbon, calls `Http::preventStrayRequests()`, uses cached config and cached routes, and ignores the developer's `.env`.
- Pure layers (`app/Support`, and `Contracts`, `Data`, `Enums`, `Exceptions` and `ValueObjects` in every `app/Modules/<Domain>`) must not reference the database layer. There is no allowlist: `TestTierArchitectureTest` fails on any model, Eloquent class or `DB` reference there. A `Data` class may name a model inside a static method whose name starts with `from`, and nowhere else.

## DATABASE RESET

- T2 uses `Tests\Concerns\RefreshesDatabaseOncePerWorker` (used by `FeatureTestCase`). It wraps `LazilyRefreshDatabase`: nothing happens until the first query, every test runs in a transaction that is rolled back.
- Each parallel worker gets its database by cloning a template database. The template is migrated and seeded once per schema fingerprint (migrations, seeders, role sources, `composer.lock`) behind a file lock; stale templates are not dropped automatically. A worker only ever drops its own `*_test_<token>` database and the template it rebuilds.
- Non-parallel runs migrate the configured database directly.
- Shared fixtures are committed once per worker database, outside the test transactions: the global configuration and the roles and permissions. Do not seed them in tests. Anything a test needs beyond that is created inside the test.
- `Tests\Concerns\WarmsPermissionRegistry` (opt-in per directory in `Pest.php`) reuses the serialized Spatie permission cache across tests of a worker and drops it as soon as a test writes to `permissions`, `roles` or `role_has_permissions`.
- Expensive or rarely needed fixtures stay opt-in per class (`$this->seed(...)` or a trait in `tests/Concerns`).
- System translations are opt-in through `$this->seedSystemCopy()`: the first call per worker runs `copy:seed`, later calls restore the same rows with one bulk insert. Never call `copy:seed` from a `beforeEach`.

## HERMETIC ENVIRONMENT

- `tests/bootstrap.php` copies only database and Redis keys from `.env`; the application itself loads `tests/Fixtures/isolated.env` (empty), so a developer's `.env` cannot change test behavior.
- Behavior switches are pinned in `phpunit.xml.dist`. Pin new ones there.

## CRITICAL HELPER

```php
$this->createTenantAndMakeCurrent()
```

Creates a tenant with the default locale and config, makes it current. REQUIRED for any test touching tenant-scoped data.

## ONE WAY

Every task has one spelling. New tests copy the example file, not the neighboring file. A guard only grows when the codebase is at zero for the new entry. No guard has an exemption list: a guard that cannot pass is a code problem, not a reason to add an entry.

| Task | The one way | Guard | Copy this |
|------|-------------|-------|-----------|
| Pick a tier | the lowest honest one (see TIERS); the directory binds the base class, a trait opts in | `TestTierGuard` (`TestTierArchitectureTest`) | `tests/Unit/Modules/Orders/Support/OrderNumberTest.php` (T0), `tests/Feature/Support/PaginatedDataTest.php` (T1) |
| Set config | `config(['key' => $value])`, in a closure, a helper and a T0.5 test | `BannedTestCallGuard` | `tests/Feature/Http/Controllers/Admin/Products/IndexControllerTest.php` |
| Move the clock | `$this->travelTo()`, `freezeTime()`, `freezeSecond()`, `travel()`, `travelBack()`; `test()->travelTo()` outside a closure; `Carbon::setTestNow()` only in `tests/Unit` | `BannedTestCallGuard` | `tests/Feature/Database/BackfillOrderNumbersMigrationTest.php` |
| Wait | never: `Sleep::assertNeverSlept()`, `$this->travel()`; no assertion on elapsed wall-clock time | convention | `tests/Feature/Modules/Shipments/Services/CarrierClientTest.php` |
| Put a fake in the container | `app()->instance(Abstract::class, $fake)`; `app()->bind()` only for a fake built per resolve or one that throws | `BannedTestCallGuard` | `tests/Feature/Modules/Reporting/Services/ReportingServiceTest.php` |
| Fake a facade or HTTP | the facade's own `::fake()`, `Http::fake()`; `swap()` only for a hand-written fake | convention | `tests/Feature/Http/Controllers/Admin/Products/ImportControllerTest.php` |
| Stand in for a class of our own | the real class, or a fake in `tests/Support` put in the container; a Mockery double (`Mockery::mock`, `$this->mock`, `test()->mock`) only where neither works | ratchet row `test-doubles` | `tests/Support/FakeCarrierClient.php` |
| Fake events | `Event::fake([XEvent::class])` with the events the test asserts, so every other listener still runs; never a new `Event::fake` with no arguments | ratchet row `event-fake-all` | `tests/Support/Feature/Modules/Invoices/Actions/DownloadInvoiceExportHelpers.php` |
| Helper | `public static` method of a `final class` in `tests/Support`, mirrored on the test path | `TestHelperFunctionGuard` | `tests/Support/Feature/Http/Controllers/Admin/Roles/DestroyControllerHelpers.php` |
| Where a test lives | the mirrored folder of the class it covers, or the topic folders of CONVENTIONS | `TestFolderMirrorsAppArchitectureTest` | `tests/Feature/Http/Controllers/Landlord/Tenants/ShowControllerTest.php` |
| Fixtures | local variables, or `$scenario = XHelpers::scenario()` | PHPStan (no object crate), `NoSharedTestFixturesArchitectureTest` | `tests/Feature/Modules/Orders/Actions/CancelOrderTest.php` with `tests/Support/Feature/Modules/Orders/Actions/CancelOrderHelpers.php` |
| Test double class | in `tests/Support`, or an anonymous class | `TestClassGuard` | `tests/Support/UnreachableBroadcaster.php` |
| Write a test | `it('does x', function (): void { ... })` or `test('a user can x', function (): void { ... })`; the closure of `it`, `test`, `describe`, `beforeEach`, `afterEach`, `beforeAll` and `afterAll` declares `: void`; never `test('it ...')`, never `->only()` | `BannedTestCallGuard` | `tests/Unit/Support/JsonTest.php` |
| Assert a value | the exact value: `toBeTrue()`, `toBeFalse()`, `toBeNull()`, `toBe()`, `toBeEmpty()`; never `toBeTruthy()` or `toBeFalsy()` | `BannedTestCallGuard` | `tests/Unit/Support/JsonTest.php` |
| Code that must not throw | call it, assert the state it leaves behind; `->throwsNoExceptions()` when there is nothing else | `BannedTestCallGuard` (`expect(true)`, `->not->toThrow()`) | `tests/Unit/Modules/Invoices/Services/InvoiceNumberServiceTest.php` |
| Code that must throw | `expect(fn () => ...)->toThrow(E::class, 'message')`; to check a field, `->toThrow(function (E $e): void { expect($e->reason)->toBe('x'); })` | `BannedTestCallGuard` (a closure that returns a bool, an arrow function that does not start with `expect(`) | `tests/Feature/Modules/Shipments/Services/CarrierClientTest.php` |
| Copy in an assertion | the translation key, or `SystemCopy::copy()`, or `__()` | convention | `tests/Feature/Http/Controllers/Admin/Products/ImportControllerTest.php` |
| Test a route | `route('name')`, never the URL of a named route as text; assert the status with its named assertion (`assertOk()`, `assertForbidden()`, `assertNotFound()`, `assertUnprocessable()`), the redirect or Inertia component or JSON shape, the database effect, and the 403, 404 and 422 paths; a form request is tested through its route, with its middleware and exception handling on | `NamedRouteUrlGuard` (`NamedRouteUrlArchitectureTest`), `BannedTestCallGuard` (`assertStatus(n)`, `withoutMiddleware()`, `withoutExceptionHandling()`); `RoutePermissionCoverageTest`, `RouteRateLimitCoverageTest` (permission and limit only) | `tests/Feature/Http/Controllers/Admin/Roles/DestroyControllerTest.php` |
| Find PHP in an architecture test | `PhpFileInspector::phpFiles()` and `callsIn()` | convention | `tests/Support/Architecture/BannedTestCallGuard.php` |
| Database | T2, `$this->createTenantAndMakeCurrent()`, nothing seeded that the template database holds | `RefreshesDatabaseOncePerWorker`, `GuardsAgainstDatabaseAccess` | `tests/Feature/Http/Controllers/Locale/SetControllerTest.php` |

## CONVENTIONS

- `phpstan-tests.neon` analyses `tests` at level 10 together with `app`. A value that enters a test as `mixed` (decoded JSON, a shared Inertia prop, a database row, a required file) is narrowed with `Tests\Support\Narrow`, `Tests\Support\NestedValue` or `Tests\Support\InstancesOf`, never with a cast or an inline `@var`; a migration is loaded with `Tests\Support\Migrations::load()`. `Tests\Support\PHPStan\PestHigherOrderProperty` and `PestHigherOrderMethod` type `expect($x)->property->...` chains. Pest binds `$this` in a closure to `Tests\FeatureTestCase` (`Tests\PureTestCase` under `tests/Unit`) through `Tests\Support\PHPStan\PestClosureThis`; a test case has no fixture properties, so a helper takes what it needs as a typed argument. Read a fresh model with `Narrow::fresh($model)` or `fresh()?->`. Give every closure parameter a type (`fn (Request $request)` for `Http::assertSent`, `fn (AssertableInertia $page)` for `assertInertia`, `mixed` for a value of unknown shape), read a model with `firstOrFail()`, `findOrFail()` or `fresh()?->`, and where a Mockery double is the only way, build it with `$this->mock()` so it carries the double's type

- Use `it()` or `test()`; no PHPUnit test classes. Pest puts "it" in front of the description of `it()`, so `it('rejects a guest')` reads as a sentence and `test()` takes a description with its own subject (`test('a guest is refused')`). `BannedTestCallGuard` fails `test('it ...')`: write `it('...')`. Every closure given to `it`, `test`, `describe`, `beforeEach`, `afterEach`, `beforeAll` or `afterAll` is `function (): void`, with or without a dataset; an arrow function is left alone
- A test never ends in `->only()` (also on `describe`): a focused test skips every other test of the run. Run one test with `--filter`
- Assert a status with its named assertion: `assertOk()`, `assertCreated()`, `assertNoContent()`, `assertFound()`, `assertUnauthorized()`, `assertForbidden()`, `assertNotFound()`, `assertUnprocessable()`, `assertTooManyRequests()` and the others the framework has. `assertStatus(n)` stays for a status without one (`assertStatus(413)`) and for a status in a variable; `BannedTestCallGuard` fails it for the rest
- A request to a named route goes through `route('name', ...)`: `$this->get(route('orders.show', $order))`, never `$this->get('/orders/' . $order->getRouteKey())`. `NamedRouteUrlGuard` (run by `tests/Feature/NamedRouteUrlArchitectureTest.php`) reads the named routes of the application and fails a `get`, `post`, `put`, `patch`, `delete`, their `...Json` forms, `json()` and `call()` in `tests`, on `$this` and as a `Pest\Laravel` function, whose URL is the text of one, also with a query string or one parameter at the end. A URL that no named route has stays text
- A test sends its request through the middleware and the exception handler: never `withoutMiddleware()` (sign in as a user with the permission the route asks for) and never `withoutExceptionHandling()` (assert the response, or call the class directly and `expect(fn () => ...)->toThrow()`)
- Assert the exact value: `toBeTrue()`, `toBe()` or `not->toBeEmpty()` instead of `toBeTruthy()`; `toBeFalse()`, `toBeNull()` or `toBeEmpty()` instead of `toBeFalsy()`
- Test files declare no `namespace` and no function (`TestHelperFunctionGuard`, run by `TestTierArchitectureTest`, also covers `Pest.php`). A helper is a `public static` method of a `final class` in `tests/Support`. Helpers of one test file live in a class that mirrors the test's path: `tests/Feature/Http/Controllers/Order/OrderShowControllerTest.php` uses `Tests\Support\Feature\Http\Controllers\Order\OrderShowControllerHelpers`. A helper that two test files share goes in a themed class directly in `tests/Support` (`Shops`, `SystemCopy`, ...)
- Folders mirror `app/`. A test named `<Class>Test.php` for an app class lives in the same folder of `tests/<tier>` as the class (`app/Http/Controllers/Landlord/Tenants/ShowController.php` → `tests/Feature/Http/Controllers/Landlord/Tenants/ShowControllerTest.php`; `app/Modules/Orders/Support/OrderNumber.php` → `tests/Unit/Modules/Orders/Support/OrderNumberTest.php`). A test of a route, flow or behavior that no single class names takes a topic name and lives in the folder of the app code it covers (a route test in the folder of its controller, a flow in the module folder, `app/Modules/<Module>`); `app/<that folder>` must exist. A test of code outside `app/` lives in `tests/<tier>/<Home>`: `Bootstrap` (`bootstrap/`), `Config` (`config/`), `Database` (`database/`: migrations, seeders, schema), `Lang` (`lang/`), `Routing` (`routes/`), `View` (`resources/views`). A test that covers the repository as a whole (an architecture guard) sits directly in `tests/Unit` or `tests/Feature` and carries a name no app class has. `tests/Unit/TestFolderMirrorsAppArchitectureTest.php` fails on every other place and has no list
- A test sets no `$this->x`. Fixtures are local variables; setup that several tests of a file share is `XHelpers::scenario()` in the mirrored helper class, returning a `final readonly` `XScenario` with typed properties next to it (a setup that returns nothing is `XHelpers::prepare()`). `beforeEach` holds only fakes, config, routes, the clock and a bare `$this->createTenantAndMakeCurrent()`; a nested `beforeEach` in a `describe` becomes a helper call that takes the scenario. The test cases declare no fixture property and are no universal object crate, so PHPStan fails on an undeclared `$this->x` (`tests/Unit/NoSharedTestFixturesArchitectureTest.php` keeps it that way)
- The next handler of a middleware test is `MiddlewareProbe::next()`; a temporary file or directory outside a faked disk is `TemporaryPaths::track($path)` with `afterEach(fn () => TemporaryPaths::delete())`
- Every test asserts an outcome it could fail on. For code that must not throw, call it directly so an exception fails the test, and assert the state it leaves behind; when there is truly nothing else to check (and no Mockery expectation, which already counts), end the test with `->throwsNoExceptions()`. Never use `expect(true)->toBeTrue()` or `->not->toThrow(...)`: `not->toThrow` swallows any exception other than the named class, and with an interface such as `Throwable` it treats the name as message text, so it passes whatever happens
- Factories in `database/factories/Tenant/` for tenant models
- Factories in `database/factories/Landlord/` for global models
- Set the tenant with `->for($tenant)`; where the tenant is reached through another model, use `state(['tenant_id' => ..])`
- Use `tests/Fixtures/` for the files a test reads, such as product import files
- `tests/Feature/Database/ForeignKeyIndexTest.php` fails for a foreign key without an index that starts with its column: give every new foreign key an index (`->index()` on a new table, `CREATE INDEX CONCURRENTLY` in a migration without a transaction on an existing one)
- Never paste copy from `resources/translations` into an assertion. Assert the translation key, or read the copy from the source: `SystemCopy::copy('admin.products.toasts.deleted', ['name' => $product->name])` (the default language, `locale: 'fr'` for French) for seeded translations, `__('errors.page_expired')` for `lang/`. In vitest, assert the key that the key-returning `t()` mock renders.
- The closure you give `toThrow()` runs after the class check and Pest ignores what it returns: assert inside it with `expect()`. `toThrow(fn (E $e): bool => $e->reason === 'x')` only checks the class, so `BannedTestCallGuard` fails on it. Variables from the test reach a `function` closure only through `use`
- Never sleep or assert on wall-clock time; use `$this->travel()` and `Sleep::assertNeverSlept()`.
- Move the test clock with `$this->travel()`, `$this->travelTo()`, `$this->freezeTime()`, `$this->freezeSecond()` and `$this->travelBack()`, not `Carbon::setTestNow()`. Outside the test closure (a helper in `tests/Support`, a `static` closure, a fixture class) call them on `test()`, for example `test()->travel(5)->seconds()`. Only tests in `tests/Unit` (T0 and T0.5 have no `$this->travel()`) use `Carbon::setTestNow()`; `tests/Feature` and `tests/Integration` never do. The framework resets the clock after every test, so no `afterEach` reset is needed.
- Set config with `config(['key' => $value])`, one call per change or one call with several keys, never `Config::set()` or `config()->set()`. The same call works in a closure, a helper in `tests/Support` and a T0.5 test.
- Bind a fake in the container with `app()->instance(Abstract::class, $fake)`, never `$this->app->instance()`, `$this->instance()`, `App::instance()` or `app()->bind()`, `singleton()`, `scoped()`, `bindIf()` or `extend()` (also on the `App` facade) with a closure that only returns a ready object. `app()->bind()` is for a fake that must be built on every resolve or that throws when it is resolved. Where a Mockery double is the only way, `$this->mock()`, `$this->partialMock()` and `$this->spy()` build it and bind it in one call. Replace a facade with its own `::fake()` and use `::swap()` only for a hand-written fake.
- An architecture test finds PHP files with `PhpFileInspector::phpFiles()` and the calls in them with `PhpFileInspector::callsIn()` (resolved names, receivers, lines). It does not walk a `RecursiveDirectoryIterator` or tokenize the source itself. A regex over the raw text stays for what is not a call: SQL in a string, a column name, a span of several statements.
- `BannedTestCallGuard` (run by `BannedTestCallArchitectureTest`) fails on every old spelling above: `Config::set()`, `config()->set()`, `setTestNow()` outside `tests/Unit`, the container bindings, `expect(true)`, `->not->toThrow()`, a `toThrow()` closure whose result Pest drops, `->only()` on a test, `withoutExceptionHandling()`, `withoutMiddleware()`, `toBeTruthy()`, `toBeFalsy()`, `test('it ...')`, a test closure without `: void` and `assertStatus(n)` for a status with a named assertion. Its list only grows when the codebase is at zero for the new entry: it has no allowlist.

## TEST DOUBLES AND EVENT FAKES

The direction is fewer Mockery doubles of our own classes. A test uses the real class, with the fakes the framework gives for what leaves the process (`Http::fake()`, `Queue::fake()`, `Storage::fake()`); a class that cannot run in a test gets a hand-written fake in `tests/Support`, bound with `app()->instance()`. Two ratchet rows in `scripts/ci-ratchets.sh` count the lines in `tests` that go the other way, and each only goes down:

- `test-doubles` counts `Mockery::mock`, `$this->mock` and `test()->mock`. A test that replaces one by the real class or a fake lowers it.
- `event-fake-all` counts `Event::fake` with no arguments. It silences every listener, model events included, so the test no longer shows what the code does; name the events the test asserts, `Event::fake([XEvent::class])`.

A pull request that raises a row fails the PHP checks step, unless a commit that touches the file says why with `[ratchet: test-doubles] <reason>` or `[ratchet: event-fake-all] <reason>`; a row at 0 takes no override.

## FLAKES

A test fails in parallel only when it shares something with another worker or with the clock. A sweep looked for each of these; what is left is listed.

- Wall-clock bounds: none left.
- The date edge: a test that builds a date with `now()` and compares it after the code under test ran fails when midnight passes in between. Freeze the clock (`$this->freezeTime()`) in any test that compares dates.
- Random data: `random_int()` and `mt_rand()` only feed ids that must not exist or an address that must differ. A new test uses a fixed value.
- Shared files: a test that writes below `storage/` or `public/` uses a name no other test uses and deletes it in `afterEach`.
- Environment: `putenv()` and `$_SERVER` writes restore what they changed (the config tests do).
- One unexplained failure in a full parallel run comes from the shared test database. Since the per-worker template database a worker only drops its own `*_test_<token>` database and clones the template under a file lock, so a failure that survives an isolated rerun is a real failure. Rerun the file alone first, then the suite once; never retry until green.

## RUNNING TESTS

```bash
# All tests (parallel)
composer test

# Specific file
php artisan test tests/Feature/ExampleTest.php

# Filter by name
php artisan test --filter=testName

# With coverage
composer testcov
```

## CI

- CI runs the PHP suite in one step: `scripts/ci-php-tests.sh` runs `composer testcov-ci` (Pest in parallel with pcov coverage, `PARALLEL_TEST_PROCESSES` processes) and uploads the Clover report to the coverage service.
- The frontend tests run in one step with `npx vitest run`.

## ANTI-PATTERNS

- **NEVER** delete failing tests to "pass"
- **NEVER** skip tenant setup for tenant-scoped tests
- **NEVER** put database access in `tests/Unit`
- **NEVER** drop a database that does not end in `_test_<token>` or `_tpl_<fingerprint>`
- **NEVER** declare a function in a test file or in `tests/Pest.php`: put it in a `tests/Support` class as a static method (`TestHelperFunctionGuard`)
- **NEVER** declare a class in a test file other than the test case named after it: put a test double in `tests/Support` (`Tests\Support\...`), or use an anonymous class when one test needs it (`TestClassGuard`, run by `TestTierArchitectureTest`)
- **NEVER** build an index, unique or foreign key on an existing table with the Blueprint or a blocking `CREATE INDEX`: set `$withinTransaction = false`, use raw `CREATE INDEX CONCURRENTLY` and add a foreign key `NOT VALID` before `VALIDATE CONSTRAINT`, as in `2025_01_01_000000_add_carrier_id_to_orders_table.php`; `ConcurrentIndexMigrationTest` checks every migration from its start date on
