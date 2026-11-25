# Agent guidance

This guide covers the example application: a small web shop platform (orders, products, customers, invoices, shipments, stock) that serves several shops from one install. The names are illustrative; the rules and the guards are the point.

## Glossary

| Product term (UI) | In code |
| --- | --- |
| shop | tenant (`app/Models/Tenant`, `TenantScope`) |
| platform administration | landlord (`routes/landlord.php`, `Pages/Landlord`, `app/Models/Landlord`) |
| shop administration | admin area (`routes/admin.php`, `Pages/Admin`) |
| basket | `Cart` |

## Product areas in code

| Area | Backend | Frontend |
| --- | --- | --- |
| Customers | `app/Modules/Customers` | `Pages/Customer`, `Components/features/customers` |
| Invoices | `app/Modules/Invoices` | `Components/features/invoices` |
| Notifications | `app/Modules/Notifications` | `Pages/Notifications`, `Components/features/notifications` |
| Orders | `app/Modules/Orders` | `Pages/Order`, `Components/features/order` |
| Platform admin | `app/Modules/{Tenants,Copy}` | `Pages/Landlord` |
| Products, coupons | `app/Modules/{Products,Coupons}` | `Pages/Product`, `Components/features/{products,coupons}` |
| Queue names | `app/Modules/Observability` | |
| Shipments | `app/Modules/Shipments` | `Components/features/shipments` |
| Shop admin | `app/Modules/{Users,Roles}` | `Pages/Admin` |
| Warehouses and stock | `app/Modules/{Warehouses,Stock}` | `Pages/Warehouse`, `Components/features/warehouses` |

Check that a route, page or action exists before a plan assumes it.

## Which source wins

When two sources disagree, the higher one wins:

1. The code and its tests.
2. The latest row of a decision log under `docs/plans/<theme>/`. Rows are only appended; a newer row names the row it replaces.
3. The plan files beside that log, and `docs/decisions/`.
4. The ticket system. Technical text in a ticket is advice, not a decision.

## House rules

**Interface and copy**

- The interface says "basket", never "cart"; the code says `Cart`. `tests/Unit/CopyWordingArchitectureTest.php` fails "cart" and "carts" in the copy.
- Every string exists in every supported language. UI translation keys live in `resources/translations/<domain>.php`, where the domain is the first part of the key in kebab case (`warehouses.title` goes in `warehouses.php`); the biggest areas have their own file per second part (`admin-products.php`, `admin-orders.php`). Each file returns one block per language, and `SeedCopyCommand` reads them through `CopyFiles`. Run `php artisan copy:seed` after adding one.

**Authorization**

- Authorize with permissions (`can(Permission::...)`), never with role names. Roles are bundles of permissions; a new permission is a case of `App\Modules\Roles\Enums\Permission`.
- Every authenticated route checks its permission or policy on the route: `->can(Permission::X)`, or `->can('ability', 'parameter')` when the URL has a model with a policy. A group-wide gate does not count, and neither does a check in the controller or the form request. `tests/Feature/Http/RoutePermissionCoverageTest.php` has no allowlist: it fails on any route behind `Authenticate` without a check.
- A page every signed-in user may use (account, notifications) gets its own case of `Permission` in the area `account`: every signed-in user holds it through a gate, and no role grants it. A check that depends on the input goes in the form request (`authorize()`, or `passedValidation()` when it needs the validated input); the route still carries a permission or a policy.

**Backend and tooling**

- Run the formatter through `composer pint`, which uses the project's formatter config. CI checks the formatting of the whole tree, not only of the changed files: Pint in the step `Formatting` of `scripts/ci-php-checks.sh`, Prettier in `scripts/ci-frontend-checks.sh`. The PHP checks step also runs `composer validate --strict`.
- The coverage service (`coverage-service.yml`) fails a pull request that lowers total PHP coverage beyond its threshold, or whose changed PHP lines are covered clearly below that total; the frontend report is uploaded under its own flag and only informs.
- No comments by default, in PHP and in TypeScript. Keep one only when a future agent would act differently because of it: a non-obvious constraint, invariant, ordering or locking rule, external quirk, or security or privacy reason. Never restate the code; a docblock carries only the types PHPStan needs. The ratchet row `comments` in `scripts/ci-ratchets.sh` counts the comment lines in `app`, `bootstrap`, `config`, `database`, `resources/js`, `routes` and `tests` and fails a pull request that adds one, unless a commit that touches the file says why with `[ratchet: comments] <reason>`.
- `bash scripts/debt-dashboard.sh --offline` runs in the PHP checks step and fails a pull request while a row is open: an Action, service, job, listener or command without a `<Class>Test.php`, a policy, middleware, rule, mail, notification or cast without one, a hook or a file under `hooks`, `utils` or `lib` in `resources/js` or a component without a test next to it, a file over 400 lines. The three rows with a cap take it from `scripts/debt-caps.json`; a cap only goes down, and the ratchet row `dashboard-cap-headroom` fails a pull request that raises one.
- Commit messages start with the ticket and a type, for example `PROJ-123 feat: ...` (`PROJ-0` when there is no ticket). The type is `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `style`, `ci`, `perf`, `build` or `revert`, with an optional scope (`PROJ-123 fix(orders): ...`). `scripts/ci-commit-messages.sh` fails a pull request with a commit that has no ticket or no type, and also accepts `fix(PROJ-0): ...` and `Revert "..."`.

## One pattern

Every task has one way to do it. New code copies the example file, not the neighboring file. Reviewers check against `docs/review-rubric.md`. The rows are in alphabetical order.

| Concern | The single way | Copy this |
| --- | --- | --- |
| Action (business logic) | a class in `app/Modules/<Domain>/Actions`: `final readonly`, dependencies in the constructor, one public `handle()`, named verb and noun without the `Action` suffix (`tests/Unit/ActionNamingArchitectureTest.php`, `tests/Unit/FinalReadonlyArchitectureTest.php`) | `app/Modules/Orders/Actions/CancelOrder.php` |
| Banned call | a rule in `phpstan.neon` (`disallowedFunctionCalls`, `disallowedStaticCalls`, `disallowedMethodCalls`) with a message that names the replacement; `allowIn` only for a path with a reason; a ban that needs the class shape stays an `arch()` test | the `config()` rules in `phpstan.neon` |
| Broadcast channel | the name starts with the prefix of a channel that `routes/channels.php` registers with its authorization, and every registered channel has `tests/Feature/Routing/<Prefix>ChannelTest.php` that joins it as the owner and is refused as someone else (`tests/Unit/BroadcastChannelArchitectureTest.php`) | `routes/channels.php` (`warehouse.{warehouse}`) |
| Catch an exception | name the exception you expect; a broad catch reports (`report($e)`, `rescue()`) or rethrows (`tests/Unit/EmptyCatchArchitectureTest.php`, no list) | `app/Modules/Shipments/Actions/CancelShipment.php` |
| Config | `Config::string/integer/boolean/float/array('key')`, never `config()` or `Config::get`; the default lives in `config/*.php`, not at the call site (`tests/Unit/ArchMigrationsConfigTest.php`) | `app/Modules/Shipments/Services/CarrierClient.php` |
| Container | constructor injection, `handle()` for jobs and commands; never `app()`, `resolve()` or `App::make()` outside providers. A class of the application declares its own binding with `#[Scoped]`, `#[Singleton]` or `#[Bind]` (`tests/Unit/ArchContainerTest.php`) | `app/Modules/Shipments/Support/CarrierRateCache.php` |
| Controller | `final readonly`, one `__invoke`, named `<Area>/<Noun>/<Verb>Controller`; it reads validated input, calls one Action or page service and returns the response: no `DB::`, lock, transaction or model write, at most 40 lines (`tests/Unit/ArchHttpTest.php`, `tests/Unit/ThinControllersArchitectureTest.php`) | `app/Http/Controllers/Admin/Products/UpdateController.php` |
| Current time | `now()` and `CarbonImmutable::parse()`; never `time()`, `Carbon\Carbon` or `Date::now()` (PHPStan) | `phpstan.neon` |
| Data for the frontend | `<Name>Data` with `#[TypeScript]`, camelCase properties; a Data class names a model only inside its static `from*` factories (`tests/Unit/FrontendDataNamingArchitectureTest.php`, `tests/Unit/TestTierArchitectureTest.php`) | `app/Modules/Products/Data/ProductData.php` |
| Dispatch an event | `XEvent::dispatch(...)`; never `event()` (PHPStan) and never `Event::dispatch(new XEvent(...))` (`tests/Unit/EventDispatchArchitectureTest.php`). An audit event, or one dispatched inside a transaction, implements `ShouldDispatchAfterCommit` (`tests/Feature/Modules/Events/AuditEventTest.php`) | `app/Modules/Orders/Events/OrderPlacedEvent.php` |
| Enum | backed by a string, UPPER_CASE cases (`tests/Unit/ArchClassShapeTest.php`) | `app/Modules/Orders/Enums/OrderStatus.php` |
| Existence and uniqueness rules | `Rule::exists(Model::class, 'column')` or `Rule::unique(Model::class, 'column')`, never a table name or the string forms. The rule skips the scope of the model, so a tenant-scoped model gets `->where('tenant_id', ...)` in the same chain (`tests/Unit/DatabaseRuleArchitectureTest.php`, no list) | `app/Http/Requests/Admin/Products/UpdateProductRequest.php` |
| File size | at most 400 lines per PHP file in `app/`, `resources/` and `tests/` (`tests/Unit/FileSizeTest.php`, no list) and per `resources/js` file (ESLint `max-lines`); split by responsibility | `app/Modules/Invoices/Services/InvoiceRenderService.php` with `InvoiceLines` and `InvoiceTotals` |
| Flash message | `Inertia::flash('success'\|'error'\|'info', ...)` with one translated line, never an array or the session (`tests/Unit/FlashMessageArchitectureTest.php`); the frontend shows it in one place and adds no success toast of its own | `app/Http/Controllers/Admin/Products/StoreController.php` |
| Foreign key and index | every foreign key has an index that starts with its column, and every `tenant_id` has a foreign key (`tests/Feature/Database/ForeignKeyIndexTest.php`, `tests/Feature/Database/TenantForeignKeyTest.php`); on an existing table: `CREATE INDEX CONCURRENTLY` with `$withinTransaction = false`, a foreign key `NOT VALID` first (`tests/Unit/ConcurrentIndexMigrationTest.php`) | `database/migrations/2025_01_01_000000_add_carrier_id_to_orders_table.php` |
| JSON (backend) | `json_decode($json, true, flags: JSON_THROW_ON_ERROR)` and `json_encode($value, JSON_THROW_ON_ERROR)`; `Json::decodeOrNull()` and `Json::encodeOrNull()` where a failure is expected (PHPStan) | `app/Support/Json.php`, `tests/Unit/Support/JsonTest.php` |
| JSON endpoint | return an `XData` or `XData::collect(...)`; a route that is not GET wraps it in a `JsonResponse` with its status; never `response()->json()` or an array (`tests/Unit/ArchJsonEndpointsTest.php`) | `app/Http/Controllers/Admin/Products/Images/StoreController.php` with `app/Modules/Products/Data/ProductImageData.php` |
| Layout | the folder table in `app/AGENTS.md`; one module per domain (`tests/Unit/LayoutTest.php`, `tests/Unit/OneModulePerDomainArchitectureTest.php`, `tests/Unit/ModuleLayoutArchitectureTest.php`, no list) | `app/Modules/Roles/Actions/AssignRoles.php` |
| Lock | one class per kind of lock owns the key, the expiry and the release; a call site never calls `lockForUpdate()` or `Cache::lock()` itself (`tests/Unit/ArchModelsTest.php`) | `app/Modules/Tenants/Support/TenantRowLock.php` |
| Log line | the shop and the request are in the log through `Context`; log a caught error as `['exception' => $e]`, never its message (`tests/Unit/LogMessageArchitectureTest.php`); report with `report($e)`, never Sentry's facade or a `Log::error` in a catch (`tests/Unit/ReportNotLogArchitectureTest.php`) | `app/Modules/Shipments/Exceptions/CarrierException.php` |
| Middleware around the next handler | it decides on the response, never on whether an exception arrived; its test sends a real request to a route that throws | `app/Http/Middleware/RunInTransaction.php` |
| Migration | no `App\` class in a migration (`tests/Unit/ArchMigrationsConfigTest.php`); a `down()` that undoes the schema change, an on-delete action on every foreign key, a default on every boolean (`tests/Unit/MigrationShapeArchitectureTest.php`); `jsonb` and the `...Tz` column types (`tests/Feature/Database/ColumnTypeTest.php`) | `database/migrations/2025_01_01_000000_create_coupons_table.php` |
| Model | `casts()` with immutable dates, `$fillable`, `Attribute::make()` accessors, `booted()` for model events and no observers; queries start at `Model::query()` with scopes on the model (`tests/Unit/ArchModelsTest.php`, `tests/Unit/NoObserversArchitectureTest.php`, `tests/Unit/NoRawTableArchitectureTest.php`) | `app/Models/Tenant/Order.php` |
| Narrow a type (backend) | a check PHPStan verifies that throws at runtime (`instanceof`, `?? throw`, `Scalar`, `StringKeyed`); never an inline `/** @var */`, `@phpstan-ignore` or `assert()` (`tests/Unit/NoInlineVarCastArchitectureTest.php`) | `app/Support/Scalar.php`, `app/Support/StringKeyed.php` |
| Per-tenant runtime state | the locale, the currency and the time zone of a shop are set by a `SwitchTenantTask` listed in `config/multitenancy.php`, never by a provider that reads the current tenant (`tests/Unit/PerTenantStateArchitectureTest.php`) | `app/Modules/Tenants/Support/SwitchLocale.php` |
| Queued class | the queue is a case of the `Queue` enum (`tests/Unit/QueueNameArchitectureTest.php`); every `ShouldQueue` class declares `$timeout` and `$tries` or `retryUntil()` (`tests/Unit/QueuedClassLimitsArchitectureTest.php`); a unique job has its own `uniqueId()` (`tests/Unit/UniqueJobArchitectureTest.php`) | `app/Modules/Invoices/Jobs/ExportInvoiceJob.php`, `app/Modules/Observability/Enums/Queue.php` |
| Rate limit | `throttle:<category>` from `config/rate_limiting.php`; a route without one is in `reads` or `writes` through its group (`tests/Feature/Http/RouteRateLimitCoverageTest.php`); limiters live in one provider under a snake_case name, with their numbers in config (`tests/Unit/RateLimiterArchitectureTest.php`) | `app/Providers/RateLimitServiceProvider.php` |
| Registry and its implementers | every implementer of the interface is in the registry list, and every entry implements it (`tests/Unit/RegistryArchitectureTest.php`) | `app/Modules/Shipments/Support/ShippingMethods.php` |
| Route | one HTTP verb for one single-action controller, a camelCase parameter, `to_route()` or `back()` for a redirect, `Inertia::render()` with a page that has a file (PHPStan, `tests/Unit/ArchHttpTest.php`) | `routes/web.php` |
| Route permission | `->can(Permission::X)` on the route, or `->can('ability', 'parameter')` with the rule in the model's policy; never a role name (`tests/Feature/Http/RoutePermissionCoverageTest.php`, `tests/Unit/ArchAuthorizationTest.php`) | `admin.products.update` in `routes/admin.php` |
| Rows of another shop | `->acrossTenants()` on the model query; never `withoutGlobalScope(s)` (`tests/Unit/ArchModelsTest.php`) | `app/Modules/Reporting/Services/OrderTotalsService.php` |
| Scheduled task | an artisan command in `routes/console.php` with `->onOneServer()->withoutOverlapping(<minutes>)` (`tests/Unit/ArchContainerTest.php`, `tests/Unit/ScheduledTaskArchitectureTest.php`) | `carts:prune` in `routes/console.php` |
| Search typed text | `whereLike($column, Like::contains($term))`; never your own escape or `lower(...) like` (`tests/Unit/LikeSearchArchitectureTest.php`) | `app/Modules/Products/Services/ProductListService.php` |
| Soft-deleted rows | `MassPrunable` with one config key for the number of days, unless the trashed rows are still read (`tests/Feature/Models/SoftDeletePruningTest.php`) | `app/Models/Tenant/Cart.php` |
| Stored class name | every event, job, mail and notification is listed in `tests/Support/Architecture/stored-class-names`; a moved one comes with its data migration or its alias (`tests/Unit/StoredClassNameArchitectureTest.php`); morph columns hold aliases of an enforced map (`tests/Feature/Models/MorphMapTest.php`) | `app/Providers/MovedClassServiceProvider.php` |
| Test | the lowest honest tier, one spelling per task, helpers as static methods in `tests/Support` that mirror the test's path, a request to a named route through `route()` (`tests/AGENTS.md`; `tests/Unit/TestTierArchitectureTest.php`, `tests/Unit/BannedTestCallArchitectureTest.php`, `tests/Unit/TestHelperMirrorArchitectureTest.php`, `tests/Unit/TestAssertionArchitectureTest.php`, `tests/Feature/NamedRouteUrlArchitectureTest.php`) | `tests/Feature/Http/RouteRateLimitCoverageTest.php` |
| Transaction | `DB::transaction(fn () => ...)`; never `DB::commit()` by hand (`tests/Unit/ManualTransactionArchitectureTest.php`) | `app/Modules/Orders/Actions/PlaceOrder.php` |
| UI string | a key in every language of its domain file in `resources/translations`; a key no code reads fails (`tests/Unit/Modules/Copy/Commands/FrontendTranslationKeysTest.php`) | `resources/translations/checkout.php` |
| Validation | a form request with array rules, read with `$request->validated()`; never `$request->validate()`, `Validator::make()` or a raw `input()` in a controller (`tests/Unit/FormRequestArchitectureTest.php`, `tests/Unit/ValidatedInputArchitectureTest.php`) | `app/Http/Requests/Admin/Products/UpdateProductRequest.php` |
| Frontend | the patterns for components, hooks, data flow, tests and file names are in `resources/js/AGENTS.md`; each is an ESLint rule at `error` on the whole tree, and `npm run types` runs with the flags of `tsconfig.json` | `eslint.config.js`, `tsconfig.json` |

- A guard (arch rule, coverage allowlist, baseline, suppression, `ignoring()`) only shrinks. Never add an entry to make a build green: fix the code, or ask the owner.
- A PR that converges a pattern adds the guard that stops it coming back, and a row here.

## Scoped guidance

More specific `AGENTS.md` files under `app`, `resources/js`, `resources/js/Components/features`, `resources/js/Components/ui`, `resources/js/Pages`, `.storybook` and `tests` add instructions for those areas.
