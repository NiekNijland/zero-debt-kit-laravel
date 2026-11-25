# BACKEND

Every task has one way to do it, written down here. Code that does it differently is waiting to be moved: copy this
file, not the neighboring code. `tests/Unit/LayoutTest.php` fails on any class that leaves the layout below. It has
no allowlist. The reasons are in `docs/decisions/0002-one-backend-pattern.md`.

## LAYOUT

The framework layer stays flat, like stock Laravel:

| Folder | Holds |
| --- | --- |
| `app/Http/Controllers/<Area>/<Noun>/<Verb>Controller.php` | single-action controllers: the folder is what it acts on, the class is one verb (`ArchHttpTest` holds the list) |
| `app/Http/Requests/**/*Request.php` | validation, and authorization that depends on the input |
| `app/Http/Middleware` | middleware |
| `app/Models/{Tenant,Landlord}` | Eloquent models |
| `app/Models/Pivots` | pivot and link models. A link table with its own `tenant_id` (`CategoryProduct`) takes `#[ScopedBy(TenantScope::class)]` and `QueriesAcrossTenants` |
| `app/Policies/*Policy.php`, `app/Casts`, `app/Rules` | model and validation helpers |
| `app/Providers/*ServiceProvider.php` | container bindings, rate limiters, macros |
| `app/View` | Blade view composers |
| `app/Support` | code that knows no domain, such as framework glue, base exceptions and traits for form requests; domain code never goes here |

`app`, `app/Http` and `app/Models` hold only the folders in this table, and no file sits directly in them.

Domain code lives in one module per domain, `app/Modules/<Domain>`, using only these folders:

| Folder | Holds | Name |
| --- | --- | --- |
| `Actions` | one change of state: `final readonly`, dependencies in the constructor, one public `handle()` | verb and noun, never `...Action`: `CancelOrder` |
| `Services` | reads that several callers share, and clients of an external system | `XService`, `XClient` |
| `Data` | spatie `Data` with `#[TypeScript]` for the frontend, or a request or response DTO of an API | `XData`; the TypeScript type has the class name, never `#[TypeScript('Name')]` |
| `ValueObjects` | every other domain value, `final readonly` where the parent class allows it | noun |
| `Support` | domain helpers that are neither an Action nor a Service: stateful per-call objects, parsers, trackers, static helpers | noun |
| `Enums` | string-backed enums with `UPPER_CASE` cases; `tests/Unit/ArchClassShapeTest.php` fails an enum in `app` without a string backing | noun |
| `Events`, `Listeners` | events and the listeners that handle them | `XEvent`; a listener says what it does |
| `Jobs` | queued work | `XJob` |
| `Commands` | artisan commands, found through `bootstrap/app.php` | `XCommand` |
| `Mail`, `Notifications` | mailables and notifications | `XMail`, `XNotification` |
| `Exceptions` | exceptions | `XException` |
| `Contracts` | interfaces | noun |
| `Facades` | facades over a service of the module | noun |

A folder may group files per subdomain (`Actions/Returns/`). Two modules never differ only by a plural or a prefix (`Stock` with `StockLevels`); `tests/Unit/OneModulePerDomainArchitectureTest.php` fails on it, without a list. Nothing sits in the module root, and controllers, requests,
middleware, models and providers never sit in a module: a module's HTTP classes live in `app/Http` under the area of
the module (`app/Http/Controllers/Shipments`), its service provider in `app/Providers`. Traits for models, requests and
controllers, global scopes and Eloquent builders sit in the `Support` of the module that owns them.

Moving a class changes its stored name in three places, so a move PR handles them:

- audit events store `static::class` in the landlord `events.name` column: a data migration renames the rows;
- database notifications store the class in `notifications.type`: a data migration renames the rows;
- a queued job, notification or mailable carries its class name in the jobs, failed_jobs and Horizon payloads: it
  keeps its old name through an entry in `App\Providers\MovedClassServiceProvider::ALIASES` for one release,
  and the next PR after that release is deployed removes the alias. An alias does not carry private properties of a
  payload queued before the move: a queued class with a private property made at dispatch becomes public in a release
  before it moves.

The stored names are listed per kind in `tests/Support/Architecture/stored-class-names` (`events.php`, `jobs.php`, `mail.php`,
`notifications.php`): every class in an `Events`, `Jobs`, `Mail` or `Notifications` folder of a module, one line each, sorted.
`tests/Unit/StoredClassNameArchitectureTest.php` fails a class that is not listed, a listed name that no longer exists and has
no alias, an alias that points at a class outside those folders, and a name listed twice. A builder who adds an event, job, mail
or notification adds its line. One who moves or renames one writes the data migration or the alias first, then replaces the
line by the new name; the old name of a job, mail or notification may stay listed while its alias exists.

`Contracts`, `Enums`, `Exceptions` and `ValueObjects` are pure, and so is `app/Support`: they never reference a model or the database
layer. A `Data` class may name a model only inside its static `from*` factories (`fromModel()`, `fromPaginator()`): that is where a model
becomes Data, as `XData::from($model)` or `XData::fromModel($model)`. Its properties, constructor and every other method stay model-free;
a helper that has to read a model for a factory sits in the `Support` of the module. `tests/Unit/TestTierArchitectureTest.php` enforces
all of it, without an allowlist. What a pure class needs from a model is handed over as a scalar or an array; an exception that describes a
model gets its factory in a `<Name>Exceptions` class in the `Support` of the module. The `Support` of a module may reference models.

## ONE WAY PER TASK

- **Business logic** is an Action. A job, command, listener or controller calls it. A controller holds no `DB::` call, lock, transaction or write method (save, update, create, delete, attach, sync, ...) except on an Action or service it gets through its constructor, and its `__invoke` is at most 40 lines; `tests/Unit/ThinControllersArchitectureTest.php` enforces it without a list. A page controller hands its props to an `XPageService`; side effects of a visit go in an Action.
- **Dependencies** come through the constructor, or through `handle()` in jobs and commands; a private method that
  needs one takes it as a parameter. A FormRequest cannot take constructor arguments: `rules()`, `authorize()` and
  `after()` take theirs as method parameters. No `app()`, `resolve()`, `App::make()` or `->make()` on a container
  outside providers; `tests/Unit/ArchContainerTest.php` fails on them and has no exceptions besides `app/Providers`. A class the container does not
  build (model, Data object, collection, notification, mail, rule) gets its collaborators as parameters from the
  container-built code that creates it; `failed()` of a queued class only calls `<Job>FailedEvent::dispatch($this, $exception)`, and a listener handles it.
  A method that overrides one of a parent class or implements one of an interface carries `#[Override]`; PHPStan (`checkMissingOverrideMethodAttribute`) fails without it.
- **Config** is read with `Config::string('key')`, `Config::integer`, `Config::boolean`, `Config::float` or `Config::array`, and
  the key has its default and its type in `config/*.php` (`(int) env('X', 60)`, an empty string for an optional value). Never
  `config()`, `Config::get` or a call-site default; `tests/Unit/ArchMigrationsConfigTest.php` fails on them in `app`, `routes` and `bootstrap`.
  `env()` is read only in `config/*.php` (PHPStan `noEnvCallsOutsideOfConfig`).
- **Validation** lives in a FormRequest, read with `$request->validated()`. Never `$request->validate()` or `Validator::make()` in a controller (data that is not a request goes through a Data object or a rule), and never a raw read (`$request->input/string/boolean/query/safe/...`) in one; `tests/Unit/ArchHttpTest.php` fails on it outside `app/Http/Requests` and lists the services that check data that is not a request. The rules of a field are an array (`['required', 'string']`), never one string, and a form request has no `authorize()` that only returns `true`: without the method the request passes and the route carries the check. Keep such a method, with `#[Override]`, only to switch off the check of a parent request. `tests/Unit/FormRequestArchitectureTest.php` fails on both.
- **Authorization** checks permissions, never roles (root `AGENTS.md`):
  - A check that needs only a permission goes on the route or the route group: `Route::can(Permission::X)`.
  - A check on a model goes in its policy and is called from the route: `->can('update', 'product')`.
  - A page for every signed-in user gets a case of `Permission` in the area `account`: all signed-in users hold it through a gate, no role grants it.
  - A FormRequest authorizes only when the check depends on the input (`authorize()`, or `passedValidation()` for validated input). The route still carries a permission or a policy: `RoutePermissionCoverageTest` has no allowlist.
  - A refusal outside the HTTP layer (service, action, model) is a domain exception that extends `AuthorizationException`, never `abort(403)`, `abort(401)`, `throw_unless(..., AccessDeniedHttpException::class)`; `tests/Unit/ArchHttpTest.php` fails on it everywhere in `app/` except middleware.
  - A controller never calls `Gate::authorize`, `->authorize(` or `abort_unless(..., 403)`; `tests/Unit/ArchHttpTest.php` fails on it with no exceptions.
- **Inertia pages** get Data objects under camelCase keys: every page prop and every key of an array the controller builds (`canEdit`, `orderLines`).
  Redirect with `to_route()` or `back()`, never `redirect()->route()` or
  `redirect()->back()`, and report the result with a translated `Inertia::flash(...)`. `Inertia::render()` names its page as text, and the page has a file in
  `resources/js/Pages`; `tests/Unit/ArchHttpTest.php` fails on a page without one.
- **JSON endpoints** return a Data object or `XData::collect($items, DataCollection::class)` (`->wrap('data')` for an envelope, `PaginatedDataCollection::class` for a page).
  A controller of a POST, PUT, PATCH or DELETE route always wraps the Data with its status, `new JsonResponse($data)` or `new JsonResponse($data, JsonResponse::HTTP_CREATED)`: laravel-data answers 201 for a bare Data on every POST, and `tests/Unit/ArchJsonEndpointsTest.php` fails on a bare Data there. Never `response()->json()`, never an array
  in a controller's `JsonResponse` (however it gets there: a literal, a variable, a service or closure that returns an array) and never an `array` return type: the guard in `tests/Unit/ArchJsonEndpointsTest.php` resolves the type of
  the argument and fails unless it is a Data or a Data collection. The JSON branch of a page controller (return type `InertiaResponse|JsonResponse`) answers with the props of the page and is out of scope. The guard lists the few
  endpoints whose body is a contract of someone else (webhooks). A payload owned by another system keeps its keys;
  `#[MapOutputName('unit_price')]` keeps a snake_case key on the wire under a camelCase property. The Inertia response is imported as
  `use Inertia\Response as InertiaResponse;`.
- **Queries** use Eloquent: `Model::query()` and local scopes on the model. Every table has a model; a link table has its pivot in
  `app/Models/Pivots`, queried as `X::query()` or `X::query()->toBase()`. `DB::table`, `DB::select`, `DB::statement` and
  `DB::raw` appear only in `database/` and in the lock classes; `tests/Unit/NoRawTableArchitectureTest.php` fails on any
  other, without a list. A SQL fragment inside an Eloquent query uses the builder (`selectRaw`, `whereRaw`, a join closure).
  Filtering, sorting and searching a list are scopes on the model, never a custom Eloquent builder or a
  query-string package. Another shop's rows are read with `->acrossTenants()`, never `withoutGlobalScope`;
  `tests/Unit/ArchModelsTest.php` fails on both and has no exceptions besides the `QueriesAcrossTenants` trait itself.
  Mass assignment stays guarded: a column that is not fillable is set with `forceFill([...])` or `forceCreate([...])`, never after `Model::unguard()` (PHPStan).
- **Models** use the `casts()` method with `immutable_datetime` and `immutable_date` for every date, `$fillable`, `Attribute::make()` accessors, `booted()` for model events (no observers) and
  typed relations with generics (`@return HasMany<OrderLine, $this>`).
- **Comments**: none by default. Docblocks only carry types PHPStan needs. Keep a comment only when a future agent would act differently because of it (a non-obvious constraint, invariant, ordering or locking rule, external quirk, or security or privacy reason); the ratchet row `comments` fails a pull request that adds one without `[ratchet: comments] <reason>` in a commit.
- **Searching** a column for what a user typed is `whereLike($column, Like::contains($term))` (`App\Support\Like`): it matches case-insensitively and takes `%`, `_` and `\` literally. Never a hand-written escape or `lower(...) like ?`; `tests/Unit/LikeSearchArchitectureTest.php` fails those.
- **Listeners** are found by discovery: a class in `app/Modules/<Domain>/Listeners` is registered by `->withEvents(discover: [...])` in `bootstrap/app.php`. A provider never registers a listener of a module by hand; `tests/Unit/ArchContainerTest.php` fails on a provider that imports one.
- **Events** that record an audit entry, or that are dispatched inside a transaction, implement `Illuminate\Contracts\Events\ShouldDispatchAfterCommit`, so a rollback never leaves a log line behind. `tests/Feature/Modules/Events/AuditEventTest.php` fails on an audit event without it. An event of the application is dispatched through its own `XEvent::dispatch(...)` with the constructor arguments; `Event::dispatch($event)` is for an event that is built earlier or comes from a vendor. Never `event()` (PHPStan) and never `Event::dispatch(new XEvent(...))` (`tests/Unit/EventDispatchArchitectureTest.php`). A broadcast event sends on a channel whose name starts with the prefix of a channel in `routes/channels.php`, and each registered channel has its test in `tests/Feature/Routing` (`WarehouseChannelTest.php` for `warehouse`); `tests/Unit/BroadcastChannelArchitectureTest.php` fails otherwise.
- **Rate limits**: every limiter is defined in `app/Providers/RateLimitServiceProvider.php` under a snake_case name, and its number lives in `config/rate_limiting.php`. A route takes a category with `throttle:<category>`; a route without one is in `reads` (GET) or `writes` (change) through the web and landlord groups, so a package route needs nothing. `tests/Feature/Http/RouteRateLimitCoverageTest.php` fails on a route without a category and has no allowlist.
- **Routes** are one HTTP verb for one single-action controller: `Route::get()`, `post()`, `put()`, `patch()` or `delete()`. PHPStan fails `resource`, `apiResource`, `singleton`, `any` and `match`. A route parameter is camelCase, the name of the controller argument it binds to; `tests/Unit/ArchHttpTest.php` fails any other.
- **JSON** is decoded with `json_decode($json, true, flags: JSON_THROW_ON_ERROR)` and encoded with `json_encode($value, JSON_THROW_ON_ERROR)`, so a failure throws instead of returning `null` or `false`. When text that is not JSON is expected, or a value may hold text from outside that is not UTF-8, use `Json::decodeOrNull()` and `Json::encodeOrNull()` (`App\Support\Json`). PHPStan fails either function without the flag in `app`, `bootstrap`, `config` and `routes`.
- **The current time** is `now()`, `now($timezone)` for the time in a timezone and `now()->getTimestamp()` for seconds: it is the clock a test moves, and it returns a `CarbonImmutable`. Never `time()`, `CarbonImmutable::now()` or `Date::now()`; PHPStan fails them.
- **Queued classes**: every class that implements `ShouldQueue` or a contract that extends it, directly or through a parent, declares `public int $timeout` in seconds (keep it at most the timeout of the supervisor of its queue in `config/horizon.php`; review checks that, the guard does not), and `public int $tries` (1 when a second run would repeat a side effect), or `retryUntil()` when the work retries until a moment. `tests/Unit/QueuedClassLimitsArchitectureTest.php` fails without them. The queue is a case of `App\Modules\Observability\Enums\Queue`, set with `$this->onQueue(Queue::X)` in the constructor; `tests/Unit/QueueNameArchitectureTest.php` fails a queue named with text in `onQueue()`, `allOnQueue()`, `$queue` or `viaQueues()`.
- **Scheduled tasks** are artisan commands in `routes/console.php`, each with `->onOneServer()->withoutOverlapping(<minutes>)`: the minutes after which a run that never finished frees the next one. `tests/Unit/ArchContainerTest.php` and `tests/Unit/ScheduledTaskArchitectureTest.php` fail otherwise.
- **Migration shape**: a migration dated 2025-01-01 or later has a `down()` that undoes its schema change (it stays empty only when the migration changes rows alone), ends every foreign key with its on-delete action (`->cascadeOnDelete()`, `->nullOnDelete()` or `->restrictOnDelete()`) and gives every boolean column a default, so a row written by the previous release during the deploy still fits. `tests/Unit/MigrationShapeArchitectureTest.php` fails otherwise.
- **Foreign keys** always get an index that starts with the column: `->index()` on a new table, `CREATE INDEX CONCURRENTLY` in a migration with `$withinTransaction = false` on an existing one. `tests/Feature/Database/ForeignKeyIndexTest.php`, `tests/Unit/ConcurrentIndexMigrationTest.php` and the foreign key migration tests fail otherwise.
- **Middleware groups**: the `tenant` group (`AbortIfNotTenantHost`, `NeedsTenant`) is the only place that needs a shop. `web_landlord` is the `web` group without the middleware that needs one, built in `bootstrap/app.php` from `$middleware->getMiddlewareGroups()['web']`, so a middleware added to `web` reaches the platform side too. `tests/Feature/Routing/RouteFilesTest.php` fails when the groups drift apart.
- **Per-tenant runtime state** (the locale, the currency, the time zone of a shop) is a `SwitchTenantTask` in `app/Modules/Tenants/Support`, listed in `config/multitenancy.php` `switch_tenant_tasks`. A provider never reads the current tenant to set config; `tests/Unit/PerTenantStateArchitectureTest.php` fails otherwise.
- **Container bindings**: a class of the application declares its own binding with `#[Scoped]`, `#[Singleton]` or `#[Bind]`. A provider binds only classes of vendors and what config chooses (a driver interface); `tests/Unit/ArchContainerTest.php` fails on a provider that binds an `App\` class.
