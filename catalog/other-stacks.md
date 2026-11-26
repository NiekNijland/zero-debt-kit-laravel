# Catalog: every row, in any stack

The patterns are the same everywhere; only the techniques differ. A pattern is never dropped because the tool is different. This file has a line for **every row** of `catalog/backend-laravel.md`, `catalog/frontend-react.md` and `catalog/tests-database-ci-docs.md`, under the row's own name in double quotes: the neutral sentence, how to count the old way, and the guard as a mechanism of `04-guards.md` C1, or "not applicable when ...". The tool behind each mechanism is one per need, named in "Tooling in another stack" below; in step 1 the lead writes it in the profile ("Differences from the kit").

Three rules for the whole file:

- **A TypeScript or JavaScript backend** takes the "Strictness" table of `catalog/frontend-react.md` as written (linter, typed linting, type checker, suppressions, type escapes, unused code, formatting, sizes), next to the backend rows below.
- **When no tool exists for a mechanism**, a small script over the source tree, run in the checks step, is the guard; all such scripts share one source reader, and each has failing examples and a real-tree case like any guard.
- **Counting**: where the cell says "sites of the old way", it means a `git grep` over tracked files with a regex tested by hand on one file, or a parser count where the construct spans lines (`02-step-1-discover.md` 1.6).

## Mechanisms

| Mechanism (`04-guards.md` C1) | PHP and React | Elsewhere |
| --- | --- | --- |
| Banned-call rule | PHPStan disallowed calls; ESLint `no-restricted-*` | the restricted-call and restricted-import rule of the stack's one linter |
| Analyser switch | PHPStan and Larastan parameters | the compiler's or type checker's own flag |
| Architecture test | Pest `arch()`, or a detector class with a PHPUnit test | a detector script on the stack's source reader, run in the guard group; an import direction between layers or modules is a lint rule (TypeScript: `no-restricted-imports` per layer) |
| Layout guard | a guard generated from the layout table | the same idea as a script: a table of folder to allowed suffix, read from the guide, checked over the file list |
| Guard class on a shared reader | a detector class on a PHP parser | a detector on the language's own parser or syntax tree |
| Size guard | a guard class; `max-lines` | the linter's size rules; a line count over the file list where the linter has none |
| Coverage test | a test over the framework's route list | a test over the framework's listing of routes, handlers, commands or subscriptions |
| Contract test over the CI files | a test that reads the pipeline file and the check scripts | the same, with the stack's one YAML parser |
| Lint rule | ESLint | the stack's one linter: list the rules, switch on by name each one the tree already meets, never rely on a preset alone |
| Formatter and refactoring tool | Pint, Rector, Prettier | the stack's one formatter in check mode and its one codemod tool |
| Ratchet row, cap, dashboard | `tools/` | the same scripts: they need `git` and a shell; what else each counting method needs is in `tools/README.md` |
| Baseline and ignores | an analyser baseline, inline ignore tags, bulk suppressions, keyed baselines | the baseline file and inline ignore comment of the stack's tools, and `tools/keyed-baseline.sh` for a tool without one; each is a ratchet row |

The same in every stack, with nothing to translate: the schema test (it queries the engine's catalog after migrating), the register guard, the guard over the documents, the guard over the guard folder, the config test, generated-file drift, the committed threshold, the package manager's audit command and the secret scan.

## Tooling in another stack

`catalog/backend-laravel.md`, "Tooling per row", names the package behind each pattern for PHP, so no project picks its own. The same holds in every stack: one tool per need, the same in every project of that stack. A project on another tool swaps it (`05-program.md` D2, "Tool swaps").

TypeScript, backend or frontend:

| Need | Tool |
| --- | --- |
| Validation of input, one schema per entry point | zod |
| Test runner, and coverage | vitest, with its own `coverage.thresholds` in the committed config |
| Module boundaries and layers | the linter's `no-restricted-imports` per layer, as in `catalog/frontend-react.md`, "Layers", in backend and frontend; no second tool |
| Moves and codemods | ts-morph, driven by the map file (`05-program.md` D9) |
| Source reader for detector scripts | ts-morph |
| Lint | eslint with a typed flat config (typescript-eslint) |
| Format | prettier with the kit's config (`templates/tool-configs.md`) |
| Unused code and unused packages | knip |
| Types shared with a client | zod: the schema is the DTO, and the client's types are inferred from it (`z.infer`), never generated or retyped |
| Dependency audit | the package manager's own audit command as JSON, piped through `audit-gate.sh` (time-boxed exceptions only, at most 7 days; no ignore list); set `ID_PATTERN` to the key that holds one advisory id and verify it in step 1 |
| Type checker | tsc with the flags of the "Strictness" table of `catalog/frontend-react.md` |
| Mutation testing | Stryker with its vitest runner (`tools/README.md`, "Mutation") |
| YAML reader for the contract test | the `yaml` package |

Every stack: the secret scan is gitleaks; the smoke suite is a test group `smoke` in the project's own test runner (`catalog/tests-database-ci-docs.md`, "Smoke suite"); and the ratchet, dashboard and baseline scripts are the kit's (`tools/`).

A stack without a table here gets one in step 1. The lead builds it by `03-conventions.md` B1 (the framework's own tool first, then what the project already uses), with one tool for each need of the TypeScript table, and writes it in the profile ("Differences from the kit"). Where the project uses two tools for one need, one is chosen there and the other is a backlog row. The run writes the table back here when it closes (`06-lock-and-audit.md` E4), so the next project of that stack does not choose again.

## Structure

| Rows | In any stack | Count the old way | Guard |
| --- | --- | --- | --- |
| "Where a class lives", "One module per domain" | the framework's own layer stays where the framework puts it; domain code lives in one module per domain under the kit's module root (`src/modules/<domain>` in a TypeScript project; in another language the same two words in its naming, as a package, a namespace or a directory), in the kit's folder set, written in the language's naming; another root is renamed in step 5; no two modules differ by a plural or a prefix. The TypeScript layout is written out under the table | files outside the layout table; pairs of near-equal module names | layout guard |
| "File size", "Method size", "Function size" | a file has at most 400 lines, tests included; a method or function at most 80; a request handler at most 40; exempt by category only | files and functions over the limit | size guard |
| "Component logic" | a UI component has at most 40 lines of logic before its markup; more is a hook, a composable or a store. Not applicable when the project has no component-based UI | components over the limit | lint rule (a local plugin) |
| "Single-use traits" | no mixin, trait or partial class used in exactly one place. Not applicable when the language has none of the three | such units with one use site | architecture test |
| "Pure layers" | interfaces, enumerations, exceptions and value objects never touch the persistence layer; a DTO names a persisted type only in its factory | persistence imports in those folders | architecture test |
| "Class shape" | handlers and actions are closed and immutable (TypeScript: each is one exported `const` arrow function, never a class, except in a class-based framework: under the layout table); an action is named verb and noun; enumerations have explicit string values; DTOs carry one suffix | classes that break each rule | architecture test |
| "Final by default" | every concrete class is closed for extension (`final`, `sealed`, not `open`); a parent class is abstract, where the framework or a real shared base needs one, and a concrete class is never extended. A hook the framework declares protected keeps that visibility. One mechanism closes classes: the formatter or linter rule that adds the keyword where the stack has one, the language's own default where it has that. The pre-scan of `05-program.md` D8 in the mocking library's terms (a proxy of a class named at runtime, a generated subclass, a spy). When the language cannot close a class (TypeScript, JavaScript), the pattern is: no class extends a project class except an abstract base | concrete classes that are open; in TypeScript, classes that extend a concrete project class | the closing rule in the formatter or linter check; the dashboard row `open-classes` with its cap as its counter, then at 0. TypeScript and JavaScript: a lint rule on `ClassDeclaration[superClass]` that allows an abstract project class and classes from packages; `OPEN_CLASS` stays empty, so the dashboard row reads 0 |

**The layout of a TypeScript backend.** Folder and file names are kebab-case. Where the framework has no layer of its own (a bare HTTP library), the framework layer is this one, under `src`:

| Folder | Holds |
| --- | --- |
| `http/routes` | the route declarations, one file per area |
| `http/handlers/<area>/<noun>/<verb>.ts` | one handler per file |
| `http/requests` | the zod schema of each entry point |
| `http/middleware` | middleware |
| `models` | the persisted types |
| `config` | the one config module |
| `bootstrap` | the composition root |

The module folders under `src/modules/<domain>` are the kit's set in kebab-case: `actions`, `services`, `data` (the zod schemas that are the DTOs), `value-objects`, `support`, `enums` (constant objects with a union type), `events`, `listeners`, `jobs`, `commands`, `mail`, `notifications`, `exceptions`, `contracts`. `Facades` has no subject. A file is named after its one export: `create-order.ts` exports `createOrder`.

A framework that has its own layers and class forms (NestJS: modules, controllers and providers as decorated classes) keeps them: its controllers stay where the framework's generator puts them, "Class shape" reads "a controller or provider class has no parent and only `readonly` constructor properties", and the kit's module folders hold the domain code the framework does not place. The first run on such a framework writes its layout table back here.

## Requests

| Rows | In any stack | Count the old way | Guard |
| --- | --- | --- | --- |
| "Controller", "Thin controller", "Thin component" | one handler per route, named area, noun and verb with the verb from one list; a handler reads parsed input, calls one action or service and returns; no transaction, lock or write in it | handlers with more than one entry point; persistence calls in the handler folder | architecture test |
| "Validation", "Form request shape", "Request input", "Component validation" | one schema per entry point, declared with it (a request class, a schema object, a form object); rules in the structured form; no method that only restates the default; the handler reads only the parsed value, never the raw request | inline validation calls; raw reads of the request in handlers | guard class; banned-call rule on raw reads |
| "Authorization", "Component authorization" | by permission, never by role name, declared at the entry point: on the route where the framework has route-level checks, as the first statement of the handler where it has not. A check on a record goes through one policy per type. Every entry point has a check | entry points without a check, from the framework's listing; role-name checks | coverage test; banned-call rule on role checks |
| "Locked properties" | state the client sends back that the client must not change is marked read-only or re-checked in every action. Not applicable when the server keeps no component state on the client | such properties without the marker | guard class |
| "Rate limit" | every entry point has a named limit registered in one place with its number in config; a default tier per group; never a number at the route | entry points without a limit | coverage test; guard class on the limiter definitions |
| "Redirect", "Flash message" | one call for a redirect to a named route; one translated message under a fixed set of keys. Not applicable for an API-only project | each other spelling | banned-call rule; guard class |
| "JSON endpoint", "Resource shape", "Page props (Inertia)", "Frontend DTO properties (Inertia)" | every response body is a DTO, and the same DTO type is what the frontend or the API client takes its types from (TypeScript: inferred from the zod schema); one naming style on the wire | hand-built response arrays or maps in handlers | architecture test; generated-file drift |
| "Error envelope" | one error shape, produced in the one error handler; no handler builds an error body | error bodies built elsewhere | guard class; a test per error class |
| "Principals that are not users", "Tool-server entry points" | a token that authenticates as a project or machine gets its permission check through the token's ability plus a policy on the route, not a second mechanism; a tool-server (MCP) entry point is an entry point of the object-level access test | routes guarded by such a token; tool-server classes | the same coverage test as the routes |
| "API pagination", "Versioning", "Token abilities" | every list endpoint is paginated with a maximum page size; the version is a path prefix; every token route names the ability it needs (a route that was open to every token meets the row through the one any-ability middleware of `10-hazards.md`, "Closing the program": it passes a token with at least one ability and refuses an empty list). Not applicable when the project serves no API to other systems | list routes without pagination; routes outside a version; token routes without an ability | coverage test over the API routes |
| "Routes", "Route declaration" | routes are declared in one place; one verb for one handler; no catch-all, multi-verb or generated resource routes, no inline handler; a parameter carries the name of the handler argument it binds to | generated, multi-verb and inline routes | banned-call rule; guard class over the route files |
| "Render a page (Inertia)" | a view or page is named by a literal that resolves to a file. Not applicable when the server renders no views | names built at runtime; names without a file | guard class over the render calls |
| "Middleware around the next handler" | a middleware decides on the response, not on whether an exception arrived, wherever the framework turns exceptions into responses before middleware sees them (most do, in at least one layer); it releases what it holds in `finally` | middleware that wraps the next handler in a transaction, a lock or a `try` | a real-request test per such middleware; guard class on the call shape |
| "Component actions coverage", "Object-level access" | every entry point that takes a record id is refused for someone else's record, proven by one data-driven test over the framework's listing | none: the test counts | coverage test |

## Container, config, bootstrap

| Rows | In any stack | Count the old way | Guard |
| --- | --- | --- | --- |
| "Dependency", "Binding of an own class" | dependencies arrive through the constructor or the function's parameters; no service location; an own class is registered by a marker on the class, the composition root registers third-party classes only | service-locator calls; manual registrations of own classes | banned-call rule; architecture test |
| "Runtime strictness", "Destructive commands", "HTTPS URLs", "Password rule" | the framework's strictness switches are set in one place at boot, written out, never through a package that sets them unseen: the data layer's strict modes, a refusal of the commands that destroy data in production, HTTPS for every generated URL in production, and one password rule that every password field uses. Not applicable for a switch the framework lacks | each switch missing from the one place; password fields with rules of their own | config test, one assertion per switch on the booted application; banned-call rule that allows the switch calls in the one place |
| "Config read", "`env()`" | the environment is read in one config module, parsed and typed there with its default; everywhere else reads the typed config | reads of the environment outside the config module (`process.env`, `os.environ`, `getenv`) | the checker's own switch where it has one, a banned-call rule where it has not; config test with an empty environment |
| "Listener", "Model events", "A registry and its implementers" | handlers of events are found by one mechanism (the framework's discovery where it has one, one register where it has not), which covers the module root, never registered by hand in several places; lifecycle hooks of a persisted type live on the type; every implementer of a registry interface is in the registry and the reverse | manual registrations; handlers in a module folder that the running application does not list; observer classes; implementers not in the registry | architecture test; config test over the registered handlers; register guard |
| "Processes and native file work", "Fake providers the running application can select by config" | a binary is run by one class, and a kind of file work has one owner; a fake provider that a keyless environment boots is source, not test support | process and file calls outside the owner class | banned-call rule per layer |
| "Scheduled work" | every scheduled task is a named command (a queued job is queued from a command), runs on one server, and holds an overlap lock with an explicit expiry | tasks without a lock or with the default expiry; inline closures in the schedule | guard class over the schedule definition; expiry against interval is a decision list |
| "Per-tenant runtime state (multi-tenant)" | state that depends on the current tenant is set by the one tenant-switch mechanism, never at boot. Not applicable when the project has one tenant | runtime config writes outside the switch | guard class |

## Data access

| Rows | In any stack | Count the old way | Guard |
| --- | --- | --- | --- |
| "Model", "Query", "Mass assignment" | one way to declare a persisted type (typed fields, immutable dates, an explicit list of writable fields); one entry to a query, with named filters on the type | each other spelling | architecture test; banned-call rule |
| "Strict models", "Relation loading" | strict ORM mode: the data layer's strict checks (no lazy loading, no silently dropped field, no read of a field that was not loaded) are on in every environment; production reports a lazy load instead of failing the request. A relation is loaded explicitly where the query is built, never by an automatic loader. The checks land by counting the violations first (`05-program.md` D2, step 4). Not applicable when the data layer has no lazy loading | the violations in the counted list; calls that switch a check off or switch automatic loading on | config test; banned-call rule; ratchet row `model-strictness-violations` during the landing |
| "Raw tables (SQL)", "Scope bypass (multi-tenant)" | no raw table or collection access outside migrations; the tenant filter is bypassed only through one named call | raw access calls; bypass calls | banned-call rule; architecture test |
| "Existence and uniqueness rules" | a validation rule that looks up a record goes through the scoped type, never through a table or collection name | lookup rules by name | guard class, no list |
| "Search", "Case-insensitive search" | text search goes through one helper that escapes wildcards and states the case rule | pattern matches outside the helper | guard class |
| "Ordering a read" | every ordering a read returns ends in a unique key, so a tie cannot make a flaky test | the orderings that read for a unique tail (`latest()`, `orderBy`) and helpers that sort an expectation | a candidate guard (`04-guards.md` C7): a call-shape guard on the chain |
| "Iterating many rows" | large result sets are read in id-ordered pages or an id-ordered stream | offset paging and unordered cursors | banned-call rule |
| "Transaction (SQL)", "Events in a transaction (SQL)" | a transaction is a closure or a scope the runtime closes; never a manual commit; an event raised inside one is delivered after commit. Not applicable when the store has no transactions | manual begin and commit; events raised inside a transaction without the after-commit marker | guard class |
| "Soft deletes", "Soft-deleted rows" | soft-deleted records are removed after one retention period, unless they are read | soft-deleting types without pruning | schema test |
| "Cached values", "Cache round trip" | only plain data is cached; every key family has a test through the production cache driver | cache writes of objects; key families without the test | guard class; the round-trip tests |
| "Locks" | a lock is taken in one class per kind of lock, which owns key, expiry and release | lock calls elsewhere | banned-call rule per layer |

## Errors, logging, background work

| Rows | In any stack | Count the old way | Guard |
| --- | --- | --- | --- |
| "Report an error", "Catch", "Log context", "Logging" | errors go to the framework's one report call; the tracker's client is touched in one place; a catch names its exception and never swallows; request and tenant ids come from the logging context, and the exception object is logged, not its message; no console output | tracker calls outside the one place; broad and empty catches; hand-written context keys | banned-call rule; guard class |
| "Jobs", "Limits of a queued class", "Queue name" | every unit of background work declares its timeout and its retries (a count, or a deadline); "queued" follows the marker through inheritance and sub-interfaces; a unique job has an explicit id; a queue or topic name is a member of one enumeration with a worker per member | units without limits; string queue names | guard class; the fit with the worker's own timeout is review where the queue is chosen at runtime |
| "Dispatch an event", "Broadcast channel", "Broadcast channels" | one way to publish an event; a client can only subscribe to a channel registered with its authorization, and every registered channel has a test for the owner and for someone else | each other publish spelling; channels published to but not registered, or registered without a test | banned-call rule; guard class over both lists |
| "Class names stored in data" | a register of every name from code that data depends on: a task name in a queue, a type discriminator in a document, an event type in a log, a serialized class name. Moves by `05-program.md` D9 | none: the guard counts | register guard |
| "HTTP clients" | one client wrapper per external system; certificate verification always on | direct calls of the HTTP library outside the wrappers; verification switched off | banned-call rule per layer |
| "Wait", "Current time and dates", "Debug and exit" | one sleep call a test can fake; one source of "now" a test can move, with immutable date values; no debug output and no process exit in source | direct system sleeps and clock calls; debug calls | banned-call rule |

## Types and spelling

| Rows | In any stack | Count the old way | Guard |
| --- | --- | --- | --- |
| "Analyser", "Static analysis", "Narrowing" | the type checker at its strictest setting, with the checks of its next major where it offers them ahead of time, on every folder that holds code of the project (source, tests, config, bootstrap, migrations, routes, the public entry file), with every strictness switch on, no baseline, no ignores; a value from outside is narrowed by a check that runs, never by an assertion or a cast | errors at the top setting in a scratch config; ignore comments | ratchet rows `analyser-baseline`, `analyser-ignore`, `analyser-level` and `analyser-switches`; a checker without a numeric level is held by `analyser-switches` alone (`tools/README.md`, "Another stack") |
| "Dead code", "Unused code", "Unused exports, files and dependencies", "Unused public methods and classes", "Unused packages" | no unused code, export, file or package; the detector runs on the whole tree with no ignore list | the stack's detector with its ignores removed | the detector in the checks step |
| "Refactoring tool", "Formatting", "Formatting in CI" | one formatter config and one codemod set per language, the same in every project, with every prepared set of the codemod tool on and no skip; CI checks the whole tree with both and writes nothing. Formatter-enforced strictness: where a formatter or linter rule can enforce a pattern (strict comparison, closed classes, private members, immutable dates, one order of class members), the rule is in the config and is the guard. A rule that changes behavior lands on its own, after a count of its sites to zero, never in the whole-tree format commit | the formatter's check mode; per rule that is still off, its sites | the checks step; ratchet rows `refactor-skip`, `refactor-sets` and `formatter-rules`, each with the stack's config file and names (`tools/README.md`, "Another stack") |
| "Strict comparison", "Text and bytes" | values are compared with the strict operator only; a string function is the one that counts characters, and code that works on bytes goes through one byte helper. "Text and bytes" is not applicable when the language's strings are not byte arrays | loose comparisons; byte-counting string calls outside the helper | the formatter or linter rule (TypeScript: `eqeqeq`); ratchet rows `loose-comparisons` and `byte-string-functions` |
| "Comments", "Commented-out code", "Marker comments" | no comment unless a future reader would act differently because of it; no commented-out code; no marker comments | the regexes of the rows, adapted to the comment syntax | ratchet rows `comments` and `todo` |
| "Migrations" | a migration never references application code | migrations that import it | architecture test |
| "Overriding a method" | the language's override marker on every overriding method, enforced by the compiler flag. Not applicable when the language has no marker | the checker with the flag on | analyser switch |
| "Decode and encode JSON" | parsing throws; the cases where failure is expected go through one helper that returns an empty value | parse calls whose failure is ignored | banned-call rule that allows only the throwing form |
| "Compare with null" | a value is compared with the language's empty value by the strict comparison operator; never by a helper function or a loose comparison | calls of the helper; loose comparisons with the empty value | banned-call rule on the helper; the linter's strict-equality rule |
| "Read the session" | session state is read from the request object the handler receives; never through a global accessor. Not applicable when the server keeps no session | calls of the global accessor | banned-call rule |
| "Translate" | one function for text from the translation files; a second source of text has exactly one helper of its own; no other translation function. Not applicable when the project shows no translated text | calls of every other translation function | banned-call rule |

## Frontend

A frontend in another component framework keeps every row; only the rule names change. The "Strictness" rows not listed here apply as written to any TypeScript code: "Linter on the whole tree", "Settled rules", "Disable comments", "Typed linting", "Type checker", "Suppressions", "Type escapes". A frontend that is server-rendered templates is under the table.

| Rows | In any stack | Count the old way | Guard |
| --- | --- | --- | --- |
| "React compiler rules" | the framework's own correctness rules for reactivity (hook rules, dependency lists, effects that set state) are errors | findings in a scratch run | lint rule |
| "Component", "Page props", "Hook", "Set of values the frontend owns", "File names" | one declaration form for a component and for a reusable piece of logic; a page takes its props as parameters; a closed set of values is a constant object with a union type, an enumeration only comes from the backend; one file-name style, no barrel files | each other form | lint rule |
| "Layers" | the kit's layer table (`catalog/frontend-react.md`, "Layers"), in the framework's naming: a file never imports from a higher layer | imports against the direction | lint rule per layer |
| "Write that returns nothing", "Write that returns data", "Read JSON for a component", "Visit in progress", "Form", "Poll", "Lists with URL filters", "Debounce" | one hook or helper per recurring job: a write, a read, progress, a form, polling, URL filters, debouncing; no hand-rolled version beside it | direct calls of the underlying library outside the one helper | lint rule (restricted properties, imports and syntax) |
| "Browser storage, media queries, fetch", "DOM events on window or document", "Viewport size", "Downloads", "Navigation" | one file per browser API ("door"); everything else goes through it | direct uses of the API outside its door | lint rule (restricted globals and properties) |
| "Dates and numbers as text" | one date module and one number module format everything a user reads | direct formatter calls | lint rule |
| "Backend enums and broadcast payloads" | types that come from the backend are generated, never retyped by hand | hand-written unions of backend values | lint rule; generated-file drift |
| "State machines", "Big contexts" | state with phases is a pure reducer over a tagged union with its own test; shared state is split by what changes together | reducers declared inside a component or a reusable piece of logic; a status string kept in plain state beside related state; shared-state objects with more than ten fields, by parser | review; rubric item 17. The count of big shared-state objects is a dashboard row the project adds, with its cap (`tools/README.md`, "Adding a row") |
| "Icons, colors, UI overrides" | one icon source, design tokens only, no visual overrides on shared primitives. Not applicable when the project has no design system | findings of the rules in a scratch run | lint rule; a token check script |
| "Logic without a test", "Component without a test", "Where a frontend test lives" | every logic file and every component outside pages and primitives has a test next to it | the dashboard rows | dashboard rows with caps |
| "Tests that run, no snapshots", "Tests that run", "Snapshots" | no focused, skipped, conditional or expected-to-fail test; no snapshot | such calls | lint rule; ratchet row `frontend-test-skip` |
| "Fake of the router library", "Fake of the form hook", "Other fakes", "Page tests" | one shared fake per library the tests replace, wired once; one typed helper for other fakes; pages render through one helper | per-file mocks of those libraries | lint rule |
| "Test set-up" | the test library's bindings, a clock fake and a resize-observer stub are in the set-up before the first component test; a stub is made per test | the set-up file | review |
| "Test file size" | the file limit holds for tests; a split file is named `<file>-<topic>`| test files over the limit | size guard |

**Server-rendered templates** (Blade, with or without Livewire, or another template language; no component framework in the browser). No linter reads a template, so a row whose guard is a lint rule has a subject only in the script files the project ships.

- Apply to the templates: "File size" (the view folder is in the size rows), "Comments" (the row counts the template language's marker), "Dates and numbers as text" (one helper formats what a user reads; a guard class over the view files fails a format call written in a view), "Icons, colors, UI overrides" where the project has a design system (the token check script reads the views).
- Apply to the script files, as written: every "Strictness" row and every other row of the table above. Not applicable when the project ships no script file of its own.
- A view holds the values it was passed, control structures over them, includes and components; never a query, a model call that loads data, a raw PHP block or an inline script. Count: `git grep -nE '@php|<script>|::(query|where|find|all)\(|DB::' -- resources/views | wc -l`. Guard: a guard class over the view files.
- A view is passed models, DTOs and scalars under camelCase keys; the DTO package is needed only where the project has a JSON endpoint. `spatie/laravel-typescript-transformer` and the generated-types drift step have no subject.
- The Livewire rows of `catalog/backend-laravel.md` hold for the component classes behind the views.

Not in the starter, which renders with React: the first project that builds the two view guards writes them back.

## Tests

**Where a test lives, in any stack:** next to the file it covers where the test runner globs the source tree (most JavaScript and Go set-ups); in a mirrored tree where tests live apart (PHP, Java, C#, Python with a `tests` package). One of the two per language in a project, never both.

| Rows | In any stack | Count the old way | Guard |
| --- | --- | --- | --- |
| "Tier" | a test uses the lightest set-up that is honest: no application boot in a unit test | unit tests that boot the application | architecture test |
| "Where a test lives" | the rule above; a flow test mirrors the domain and its entry point | tests the rule does not place | layout guard |
| "Helpers", "Extra classes in a test file", "Fixtures" | helpers are named functions in one support folder, never in a test file or a shared base class; a test builds its own state in local variables, never in fields set up for every test | helper functions in test files; shared mutable fixtures | guard class |
| "Hermetic base set-up" | hermetic tests: the one base set-up of every test that boots the application refuses a real network request and a real child process, fakes sleeping, freezes the clock and resets every pinned id or random value; a test fakes what it expects and moves the clock itself (TypeScript: the set-up file of the test runner, with fake timers and a request interceptor that fails an unhandled request) | the calls missing from the base set-up; real sleeps and calls that allow real requests in tests | a test of the base set-up; the guard class of banned test spellings |
| "Architecture presets" | the test framework's prepared architecture rule sets, where it has them, are the floor under the kit's own guards, with no exception list; a set that contradicts the kit's layout, or the framework's own extension points, is not called and each of its rules the kit adopts is held by a named kit guard. Not applicable when the stack has no prepared sets: the kit's guards are the floor | the sets in a scratch run; exception calls on a set | the guard test that calls the sets; a banned spelling for the exception call |
| "Config, clock, container in tests", "One spelling in a test", "Declaring a test", "Return type", "Data sets", "Asserting a value", "Asserting a status", "Code that must not throw", "Focus", "Groups" | one way per test construct in the project's one test framework: to name and declare a test, feed it data, assert a value exactly, assert a status by name, set config, move the clock, replace a service; no focused test | each other spelling | one guard class whose list of banned spellings grows as each reaches zero |
| "The real path", "Request to a named route" | a test goes through the real middleware and error handling; a URL comes from the framework's reverse lookup, never written out | calls that switch them off; literal URLs a named route has | banned-call rule; a guard that takes the route table as an argument |
| "Stand-in for a class of the project", "Fake events and queues" | the real class or a hand-written fake before a mocking-library double; an event, queue or message fake names what it fakes (an empty list fakes everything) | doubles of own classes; blanket fakes | ratchet rows `test-doubles`, `event-fake-all` and the project's `queue-bus-fake-all`, with the stack's spellings |
| "Private state" | a test never reaches private state by reflection or a visibility override | such calls | banned-call rule |
| "Imports" | not applicable outside Pest: the rule exists because a Pest test file is not a class | none | none |
| "File size" | the file limit holds for tests | test files over the limit | size guard |
| "Architecture tests" | guards sit in one fast group, one file per topic, on the shared reader, and every one runs in the checks step | guards with their own parsing; guards outside the group | banned-call rule; contract test over the CI files |
| "Guard shape" | every guard test has failing examples, passing examples and one real-tree case; a detector holds no literal path of the repository | detectors with a path literal; guard tests without one of the three parts | guard over the guard folder |
| "Test debt no other row shows" | a facade double, a wall-clock assertion, an unseeded random value and a timer-dependent delay are each fixed or counted: a counter that runs a tool and not a grep is a command row with a cap | per kind, the grep or the tool run | a command row with a cap and its `cap-` row |
| "Skips" | no skipped, pending or conditionally skipped test | the framework's skip spellings | ratchet row `test-skip` |
| "Every test file asserts" | every test file holds at least one assertion; the runner fails a test without one | none: the guard counts | guard class; the runner's strict setting |
| "Coverage of behavior", "Coverage of support classes", "Characterization tests" | every class that holds behavior, and every class the framework calls for you, has a test that names it and then its own test file; a class to be split has a test that pins it first | the dashboard rows `classes-no-test-names`, `classes-without-own-test` and `support-classes-without-own-test`, with `BEHAVIOR_CLASSES`, `SUPPORT_CLASSES` and `TEST_NAME_SUFFIX` set for the stack (`tools/README.md`, "Another stack") | dashboard rows with caps |
| "Route coverage" | every route or handler is hit by at least one test, proven by recording the hits during the suite | routes not in the recorded set | coverage test |

## Database

| Rows | In any stack | Count the old way | Guard |
| --- | --- | --- | --- |
| "Tests on the production engine" | the test suite runs on the production store's engine, in the same major version; never an in-memory or other engine | a test connection whose engine differs from the production default | config test; contract test over the CI files (the service image equals `DATABASE_IMAGE`) |
| "Column types" | structured data sits in the engine's native JSON type and a moment in time is stored unambiguously in UTC; the per-engine cells of the catalog hold, whatever the language. Not applicable when the store is a document database: the section below replaces this row and the six after it | columns of the migrated schema that hold JSON in a text type, and time columns without the engine's form, by a query on the engine's catalog (a grep of the migration tool's calls only where every migration goes through it) | schema test |
| "Foreign key index", "Foreign keys (multi-tenant)" | every foreign key column has an index that starts with it; every tenant column has a foreign key | reference columns without an index, and tenant columns without a constraint, by a query on the engine's catalog | schema test |
| "Index on an existing table", "Foreign key on an existing table" | built in the engine's non-blocking form, the foreign key after the orphan check; the per-engine cells hold | migrations that add an index or a foreign key to an existing table without the form, by a parser over the migration files or over the SQL the migration tool prints | guard class over the migration files, from one named file on; what the guard cannot decide: review, rubric item 5 |
| "Type change on a busy table" | never a plain type change on a table of the profile's big-table list; the per-engine procedure holds | type changes in the migration files against the big-table list | guard class; the migration plan per table: review, rubric item 20 |
| "Polymorphic types" | a type discriminator column holds a short alias from one enforced map, never a class or type name from code | discriminator values in the seeded database that are not aliases of the map | config test (the map is enforced); schema test with the seeded data |
| "Encrypted columns" | every field that holds a secret or personal data the profile lists carries the data layer's encryption marker, and the list of such fields is a register | fields with the marker against the register | register guard |
| "Migration shape" | a migration can be undone, names what happens to dependents on delete, and adds no column the previous release cannot write. With a declarative migration tool that has no down migrations: a forward migration that restores the previous shape is written and tested in the same PR | migrations that fail any of the three | a guard class that holds from one named file on |

### A document database

The patterns of the SQL rows, in a document database's terms. Not proven in the starter: the first project that builds a guard here writes it back.

| Pattern | In a document database | Count the old way | Guard |
| --- | --- | --- | --- |
| The schema is declared in migrations | every index is created in a migration; none by hand or at runtime | indexes no migration declares; index calls outside migrations | a schema test that compares the indexes after migrating with a declared contract |
| Expiry is the engine's job | a TTL index, declared in a migration; application pruning only where a rule needs logic | scheduled deletes on a date field | the same test |
| Access goes through models | no raw collection access outside migrations; a raw read skips scopes and casts | calls that reach the driver's collection | banned-call rule |
| Maps are typed | a free-form map has a declared shape (a cast or a DTO) and one spelling for "empty" | object casts and untyped map writes | analyser types; banned-call rule |
| Ids have one type | conversion between the engine's id type and a string happens in one place | id casts and plucks of the raw id elsewhere | banned-call rule |
| References are checked | the engine enforces no foreign key: each reference field has an index and an orphan check | reference fields without an index | the same schema test; an orphan count as a dashboard row the project adds (`tools/README.md`, "Adding a row") |

## Rows that apply as written

These rows of `catalog/tests-database-ci-docs.md` are technique-neutral already: the sentence, the count and the guard of the row hold in every stack as that file writes them, with the stack's tool for the mechanism.

- Proof that tests can fail: "The break sample", "Mutation testing".
- Runtime and contracts: "Smoke suite" (browser smoke: in a browser where the project has a frontend, as HTTP requests where it has none), "Seeded test user", "Runtime check", "Contract with a sibling repository", "Secrets".
- CI and dependencies: "Dashboard caps", "Baselines", "Manifests", "Generated files", "Dependency audits", "Packages behind", "Dependency bot", "Commit messages", "Coverage", "Check scope", "Skipped steps", "CI duration", "Lists stay deleted".
- Translations and copy: "Languages", "Literal keys", "Unused keys", "Banned words in copy", "Translation source". Not applicable when the project shows no translated text.
- Dead code: "Test-only routes and methods", "Unused exports of tests (frontend)", "Feature flags on for everyone".
- Docs: "`AGENTS.md` set", "Paths in the guides", "Example files", "Review rubric", "Claims about guards", "Plans", "Age", "Lands with".

Three rows of that table name Composer and are written here for any package manager:

| Rows | In any stack | Count the old way | Guard |
| --- | --- | --- | --- |
| "Advisory-blocking dependency" | a dependency with a known advisory cannot be installed: the mechanism of the package manager that refuses it at install time (Composer: a conflict package; npm, pnpm and others: none, so the audit in the checks step is the block and the row has no subject) | whether the mechanism is in the manifest | ratchet row `advisory-block` where the mechanism exists; its entry leaves `MIN_ROW_TARGETS` where it does not |
| "Manifest floor", "Dependency update" | the manifest states the versions that were tested as its lower limits; one named command updates within the major and raises the limits, run by a person and hooked to no package-manager event | requirements whose lower limit is below the lock file; update commands under an event hook | the package manager's dry run of the raise in the checks step; contract test over the CI files |
| "Composer scripts" | the task runner of the stack has the same six names in every project of that stack: the one local command, the check of form, the check of types, type coverage, the suite with coverage, and the write-mode formatter; each check command is also a line of `scripts/checks.sh` (TypeScript: the `scripts` of `package.json`, under the same names) | names missing; commands that are no line of the checks step | contract test over the CI files |

## The version floor in another stack

The floor tables of the two stack catalogs name PHP and React versions. In another stack, build the same table in step 1: for each row above, the oldest release of the language, framework, checker, linter and test runner that has the feature the row needs (the override marker, the strict flags, the closed-class keyword, bulk suppressions or another baseline mechanism). The highest of those per platform part is the floor.
