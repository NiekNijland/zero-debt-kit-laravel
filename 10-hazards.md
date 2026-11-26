# Hazards: the traps, and what to do

Read before the first code change. Each line cost a red build, a wasted push or a production risk in an earlier run, and each ends in what to do. Only traps that no other file states are here; a rule that has its own place (the process in `07-process.md`, the CI shape in `04-guards.md` C4, measuring in `02-step-1-discover.md` 1.6) is not repeated. The lessons behind them are listed in `record-source-project.md`; a run does not need it.

## A test with exception handling off

A middleware that wraps the next handler in a transaction never rolls back on a server error: the framework's router turns the handler's exception into a response before control returns to the middleware, so the wrapped call returns normally and the transaction commits. A test with exception handling off does see a rollback, so it passes on the bug. A test that sends a real request and asserts a server error finds it.

The shape of the fix (PHP example: `reference-project/app/Http/Middleware/RunInTransaction.php`):

- inside the transaction: when the response carries an exception (the framework keeps the exception it rendered on the response object), keep the response and rethrow the exception, so the transaction rolls back;
- outside the transaction: catch it and return the response that was already rendered. The handler has reported the exception once; rendering or reporting it again would double the report.

It is general. Any middleware that relies on an exception propagating through the next handler has the same hole: a transaction, a lock released only in a catch block, a metric or audit line written "on success" without looking at the response. This is the one place that explains it; the rule is the catalog row "Middleware around the next handler". So:

- count the middleware that wraps the next handler, and read each one;
- each such middleware has a test that sends a real request to a route that throws, with exception handling on, and asserts the side effect did not happen;
- no test switches off exception handling or middleware; a guard bans both calls (`catalog/tests-database-ci-docs.md`, "The real path");
- moving tests to the real path is a bug-finding step: plan it as one (`05-program.md` D2, step 4), and put what it finds in the same PR under "Behavior that changes on purpose".

## Stored names

The procedure for moving or renaming a class whose name is stored is `05-program.md` D9. The traps around it:

- Look for stored names in every kind of place before the first move: audit or event tables, notification type columns, queued jobs, mails and notifications (also in failed-job and queue-dashboard payloads), polymorphic type columns, cache entries, config files that import application classes (queue config, schedules, health checks). Each kind gets its file in the register.
- Enforce a map for polymorphic types, so those columns hold short aliases, not class names.
- Data and enumeration names become frontend type names in the generated types file: regenerate it in the same PR and update the imports.
- Route names must not change in a controller rename.
- After moving a file, grep it for path arithmetic: the depth changes with the folder (see "Path arithmetic and moved code" below for the three more traps of a move into a namespace).
- **A serialized closure is a stored name**: its scope class (the class that defines it) and every class its body names or captures are written into each job of a chain (and into the options of a batch). Moving any of them fails every in-flight chain at unserialize with "class not found", in the deploy direction and in the rollback direction, and no test, analyser or guard sees it. Grep for closures given to `Bus::chain`, `Bus::batch`, `Queue::push`, `dispatch(function`, `->catch(`, `->then(` and `->finally(` in instance methods: the defining class, every class named in the body and the type of every captured variable go in the register (a kind of its own, with failing examples for the defining class, a body class and a captured object, and near misses such as a callback that runs at once). For new code a static closure that names only constants and calls a small invokable job cannot be broken by a later move.
- **A private property set at dispatch** is keyed by the class name in the payload (even one declared in a parent, keyed by the child's name): a move changes the key and the value is lost. Scan by reflection for queued classes with private state and read where each is set, including the hooks the queue calls while it builds the payload (`retryUntil`, `middleware`, `backoff`, the constructor). The rule is "not private" (protected is not keyed by class name); the change costs a payload queued before the release its value.
- **A text replace of a stored payload is unsafe twice**: a chain stores the next jobs as serialized strings inside the serialized first job, so every `s:<length>:` and `O:<length>:` of the inner strings must be recomputed; and a serialized closure is signed with the application key, so rewriting its names invalidates the signature. The data migration re-signs with the key, or leaves closure rows alone and lets alias removal wait for their pruning (the retention of the failed-job and batch tables decides how long).
- **The alias is one lazy autoloader on the one map**, not a list of `class_alias` calls: when a name has no file and the other name of its map line loads, it becomes an alias. That is right before the move, after it and during a rollback, with no eager loading at boot. It acts only when the autoloader that serves the application finds no file for the name (`ClassLoader::findFile`): the analyser's own source locator includes class files through a trapped autoload, and a loader that autoloads another class there makes it report "Cannot redeclare class" as errors. Test it with one fixture pair per state (a class with only the old file, one with only the new file); `class_alias` cannot be undone in a process, so each pair serves one test.
- The framework's discovery of listeners and commands scans its stock folders. A listener that moves into a module is no longer found and stops firing, and a test with an event fake stays green. Extend discovery to the module root in the PR that moves the first one; the config test of the row "Listener" fails a class that is not registered (`catalog/backend-laravel.md`).

## Path arithmetic and moved code

Moving functions or files into namespaced classes breaks four things that no analyser sees at once; the suite finds them within a few runs:

- an unqualified global class (`Closure`, `ZipArchive`, a mocking class) and a relative name (`Illuminate\Validation\Validator`) now resolve inside the new namespace: import or qualify them;
- `__DIR__` and `__FILE__` change depth: rewrite to `dirname(__DIR__, n) . '/<old dir>'`, and grep every moved file;
- a file with braced `namespace { }` blocks is not top level: unbrace it before the move;
- a class declared in the same test file is referenced by `\Name` and `use Name;` in the new files;
- test code bound to a namespace breaks when namespaces merge: a native-function shadow (a function declared in the namespace of the code under test, so a test can make `tempnam` fail) stops applying, and two namespaces that both defined the same shadow must be merged into one function that honours both flags (`git grep -nE '^namespace App' -- tests/Support | grep -v Architecture`);
- the depth of `__DIR__` arithmetic is computed from the map (old and new segment counts), so the list of files to read is exact; a path built from an old folder name as a string is found by grepping every script and config for the folder names the move removes (a category exclusion by folder name dies with the folder: exclude by class name).

## Strict models

Read first whether the strictness is already on: where `Model::shouldBeStrict()` already throws in every environment, a "recording" PR relaxes production, and a resolver that catches `Throwable` has been swallowing the lazy-loading exception (the row it writes silently lacks its relation: with a recording handler the relation loads and the row is complete, which is behavior that changes). Switching the framework's model strictness on in an application that already runs throws in every code path that lazy-loads a relation, fills an attribute that is not fillable, or reads a column the query did not select, and the tests cover only some of those paths. In production two of the three throw as well. So the switches never arrive as throwing checks:

- they land recording first, with the counted list and its row (`05-program.md` D2, step 4), and stay that way over at least one full step with its runtime check, so the production-like environment is asked too;
- a violation is fixed at the query (a `with()`, a full select, the attribute made fillable or set with `forceFill()`), never by a handler that swallows it and never by switching a check off for one model;
- the switch to throwing is its own PR, under "Behavior that changes on purpose", when the list is empty (step 8). Its rollback is one revert, and the PR says so;
- a queued job that was serialized before the switch restores its models without relations: read the job classes for relations they use after the restore.

## Runtime strictness in production

- `URL::forceHttps()` behind a proxy that ends TLS: where the trusted-proxy setting is wrong the application already generates `http://` links; forcing the scheme hides that and can break a signed URL that was signed under the other scheme. Check the proxy setting and re-issue long-lived signed links in the same PR.
- `DB::prohibitDestructiveCommands()` also stops a deploy script that runs a fresh migration against a production-like database that reports itself as production. Read the deploy and seed scripts for those commands first.
- `Date::use(CarbonImmutable::class)` makes every date the framework hands out immutable: a line that calls a modifier and ignores the result (`$date->addDay();`) silently stops working. The row `mutable-dates` does not see those: before the switch, count the modifier calls written as a statement of their own (`git grep -nE '^[[:space:]]*\$[A-Za-z_>-]+->(add|sub|set|startOf|endOf|modify)[A-Za-z]*\(.*\);$' -- app | wc -l`) and read each one.
- A stricter password rule applies to new passwords only; say in the PR what happens to existing ones (nothing, until they change).

## Big moves

- Big mechanical moves conflict with every open PR: run them alone, on an empty queue, and merge them the moment they are approved (`07-process.md` section 6).

## The refactoring tool

- It can rewrite code into a form the analyser rejects, and the analyser's fix can be what the tool rewrites again: two checks that each demand the other's old form. Keep the form the base branch settled on, and fix the idiom by hand before the pass so that neither tool touches it (`05-program.md` D2, step 2); never a skip entry, never an ignore.
- It does not read the route files, and why: `templates/tool-configs.md`, "PHP refactoring tool". The dry run in CI uses the same paths.
- Its `codingStyle` set and the formatter touch the same constructs. Run the tool, then the formatter, then the tool's dry run: the tree is settled when the dry run is clean after the formatter.
- It can strip a property default on a queued class, which breaks payloads queued before, and change the visibility or the read-only marker of a property the framework reads. Diff every queued class and model after a pass; keep the defaults. Once a class is closed, it removes unused public method parameters: a handler that type-hints a request class only to trigger validation must read from it.

A project that has a bot that commits fixes (kept or not yet decided in step 1) meets one more trap: the bot puts imports and markers at different positions per branch, which gives duplicate imports (fatal) after a merge. Take the base's version of every file the bot touched that the PR does not really change, and grep for duplicate imports after every merge.

## Checks and tooling

- CI that passes thousands of file names in one environment variable fails with "argument list too long": write the list to a file and pass the file.
- After deletions, the lead runs the full analysis, the refactoring dry run and the formatter check before the push: a scoped analysis misses references to deleted classes in config allowlists.
- Local static analysis may need env overrides so the framework boots without services the host lacks, and a worktree needs an env file or the analyser cannot boot: the profile says which.
- Local dependencies drift from the lock file; a local analyser result that differs from CI is usually that. Install from the lock file first.
- An `upload || echo` inside a shell function switches off stop-on-error for that function: never use `||` to soften a step (`catalog/tests-database-ci-docs.md`, "Skipped steps").
- A formatter picks its parser from the file extension: an unusual extension needs a parser entry, not an ignore. Removing a formatter rule can reformat the whole tree: do it alone, as the last commit after merging the base.
- A lock file's content hash can cover manifest fields you would not expect: refresh the hash only, and check the lock diff holds nothing else.

## Stack facts that cost time

- `config:cache` bakes the environment at cache time: changing a setting needs the optimize step again, and a one-off database needs the cache path pointed at a file that does not exist. Two trees need one storage folder, or a file uploaded through one is unreadable by the other. A fake provider's model name must equal the model row's name. A container may lack `pkill`.
- A test that reads a route name is not hermetic against a route cache (an unnamed route gets a generated name). A test run writes its own error lines into the same log file as a stack booted for a runtime check, so a log count after a test run is wrong unless the window is cut.

## Tests

- A top-level constant copied into split test files is a duplicate global constant: give each file its own name or move the constant to the support folder.
- Typed fixtures expose methods that only untyped test calls kept alive; the dead-code rule then fails on the whole tree. Delete them: that is the rule working (the second dead-code pass, `05-program.md` D2, step 9).
- Split test files that shared hoisted mocks or set-up inherited from an earlier test fail on their own. Give each split file its own set-up.
- A shared frontend fake changes timing: setting a page after mount must be inside the test library's act wrapper, and tests of the real library opt out of the fake.
- A tie in an ORDER BY is a flake that a test pins by luck (the order the database chose); a helper that sorts an expectation hides it on purpose. See the row "Ordering a read" (`catalog/backend-laravel.md`). An unseeded random value fed to a unique column fails once in many runs; a UI delay that depends on the clock (a hover timer) fails on a slow runner: fake the timer.
- A dataset label that is an integer string (`'1' => 1`) becomes an integer key and loses its name: prefix such labels. A codemod that names datasets from their values yields names equal to the values: the review reads them.
- A frozen clock with a lock wait and a faked sleep spins forever and a killed worker (exit 137) aborts the whole parallel run: run the workers with a memory limit, so the spin dies as a fatal error that names the test. Running each file alone is no answer (each process migrates the database again).
- A test file that never ran (a missing opening tag) and a test that asserts nothing (a chained call that looks like an assertion and is not one) both pass. The guard "Every test file asserts" catches the file; the reviewer's break sample catches the single test.
- A full-suite failure that does not reproduce in isolation is usually a parallel teardown race, two tests writing one fixed directory, or static state a test did not reset. Re-run in isolation before chasing it; give each test its own directory. The flake sweep of step 4 finds these.
- A factory that picks a random value makes a count or equality assertion fail once in many runs: pin the value in the test.
- A browser smoke test waits for the page, never for a number of seconds, and reads its user from the seed; a browser version that moves with the runner image makes it fail with no code change, so the browser is pinned like every other tool (`04-guards.md` C4).
- With the clock frozen by the base set-up, two rows made in one test carry the same timestamp, and "the newest first" has no order. A test that asserts an order travels between the two writes, and code that needs a stable order sorts by the id as well.
- A frozen clock also freezes a rate limiter, a cache expiry and a signed URL: a test that waits for one to pass travels past it. A faked sleep returns at once, so a loop that polls until a deadline needs the clock moved inside the fake, or it spins.

## Frontend

- Fixing effect lint rules changes timing. In code with timers, subscriptions and forms that is behavior: hand-written, one effect at a time, with the existing tests.
- Renaming hundreds of files must keep page resolution and story paths working. Helper modules inside a glob-resolved pages folder must not match the page glob.
- The unused-code tool flags exports of test helpers no sibling imports. An entry file referenced only from a server template looks unused to it: declare it as an entry, never as an ignore.

Traps of one framework are in its catalog: `catalog/backend-laravel.md`, "Framework traps".

## Closing the program (step 10)

- A dashboard row that counts by a folder regex reads zero when a folder is missing from the regex (25 untested classes in `Support` folders). Prove every zero by listing the files the pathspec matches and the folders of the tree that match no row.
- Serialized closures carry class names in their bodies, not only in their scope. An alias list built from the class moves misses a class that only a closure body names; the runtime check with old-tree payloads found three. Read every serialized closure of the old tree and list the names its body resolves, before the release that moves them.
- The first end audit finds the rest of the definition, not nothing: two auditors scored 7 and 6.5 although the dashboard was green. Make the dashboard hold every number the definition states (type coverage, pinned images, path conditions, inline casts) before step 10 starts, and budget three audit rounds.
- Builders cut from one base collide when one moves tests and another writes tests for the same classes. Merge the move branch first and cut the test builders from it.
- Run the guard group on the tip of every PR in a chain; a fix that lived on the top merge belongs on the lower PR, or the lower PR is red alone.
- A private package host that refuses authentication in a non-interactive session turns the online dashboard rows into UNKNOWN. Put one measuring command that works without it (a scratch manifest) in the profile.
