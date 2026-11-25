<?php

declare(strict_types=1);

use App\Support\RouteMatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Tests\Support\ArchChecks;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Globs;

uses()->group('arch');

arch('routes are registered only in routes/ and bootstrap/app.php')
    ->expect([Route::class, Router::class])
    ->toOnlyBeUsedIn([RouteMatcher::class, 'Tests']);

test('no class under app/ loads a route file', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $offenders = [];

    foreach ($inspector->phpFiles($root . '/app') as $file) {
        if (ArchChecks::loadsRouteFile($inspector->callsIn($file))) {
            $offenders[] = substr($file, strlen($root) + 1);
        }
    }

    expect($offenders)->toBe([]);
});

test('dependencies come through the constructor or handle(), never through the container', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $calls = static fn (string $source): array => ArchChecks::containerCallLines($inspector->calls($source));
    $offenders = [];

    foreach ($inspector->phpFiles($root . '/app') as $file) {
        $class = 'App\\' . str_replace('/', '\\', substr($file, strlen($root . '/app/'), -4));

        if (str_starts_with($class, 'App\Providers\\')) {
            continue;
        }

        $lines = ArchChecks::containerCallLines($inspector->callsIn($file));

        if ($lines === []) {
            continue;
        }

        foreach ($lines as $line) {
            $method = ArchChecks::methodAtLine($file, $line);

            $offenders[] = $class . '::' . ($method ?? 'outside a method') . ' line ' . $line;
        }
    }

    expect($offenders)->toBe([])
        ->and($calls('<?php $a = resolve(Foo::class);'))->toHaveCount(1)
        ->and($calls('<?php $a = app(Foo::class); $b = \app();'))->toHaveCount(2)
        ->and($calls('<?php $a = App::make(Foo::class); $b = \Illuminate\Support\Facades\App::call($x);'))->toHaveCount(2)
        ->and($calls('<?php $a = app()->make(Foo::class); $b = $this->container->make(Foo::class); $c = $container->make(Foo::class);'))->toHaveCount(3)
        ->and($calls('<?php $a = Container::getInstance();'))->toHaveCount(1)
        ->and($calls('<?php trait T { public function f(): void { resolve(Foo::class); } }'))->toHaveCount(1)
        ->and($calls('<?php final class J { public function failed(): void { app(Foo::class)->handle(); } }'))->toHaveCount(1)
        ->and($calls('<?php final class M extends Model { protected static function booted(): void { static::saved(static fn () => resolve(Foo::class)->forget()); } }'))->toHaveCount(1)
        ->and($calls('<?php $a = App::environment(); $b = App::getLocale(); $c = Hash::make($x); $d = $this->resolve($x); $e = $factory->make();'))->toBe([]);
});

test('every scheduled task is a command that runs on one server', function (): void {
    $tasks = static function (string $source): array {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($source),
            static fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));
        $found = [];
        $current = null;
        $depth = 0;

        foreach ($tokens as $index => $token) {
            if ($current === null && $token->is(T_STRING) && $token->text === 'Schedule' && ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON) === true) {
                $current = ['line' => $token->line, 'onOneServer' => false];
            }

            if ($current === null) {
                continue;
            }

            if ($token->is(T_STRING) && $token->text === 'onOneServer' && ($tokens[$index - 1] ?? null)?->is(T_OBJECT_OPERATOR) === true) {
                $current['onOneServer'] = true;
            }

            $depth += match ($token->text) {
                '(', '[', '{' => 1,
                ')', ']', '}' => -1,
                default => 0,
            };

            if ($token->text === ';' && $depth === 0) {
                $found[] = $current;
                $current = null;
            }
        }

        return $found;
    };
    $offenders = [];
    $scheduled = 0;

    foreach (Globs::of(dirname(__DIR__, 2) . '/routes' . '/*.php') as $file) {
        if (preg_match('/Schedule::call\(/', (string) file_get_contents($file)) === 1) {
            $offenders[] = basename($file) . ' schedules a closure';
        }

        foreach ($tasks((string) file_get_contents($file)) as $task) {
            $scheduled++;

            if (! $task['onOneServer']) {
                $offenders[] = basename($file) . ' line ' . $task['line'];
            }
        }
    }

    expect($scheduled)->toBeGreaterThan(0)
        ->and($offenders)->toBe([])
        ->and($tasks('<?php Schedule::command("a")->daily(); Schedule::command("b")->onOneServer()->daily();'))->toBe([['line' => 1, 'onOneServer' => false], ['line' => 1, 'onOneServer' => true]])
        ->and($tasks('<?php Schedule::call(static function (): void { run(); })->hourly();'))->toBe([['line' => 1, 'onOneServer' => false]])
        ->and($tasks('<?php Schedule::call(static function (): void { run(); })->hourly()->onOneServer();'))->toBe([['line' => 1, 'onOneServer' => true]]);
});

test('a class of the application declares its own container binding with #[Scoped], #[Singleton] or #[Bind], not a provider', function (): void {
    $root = dirname(__DIR__, 2);
    $bindings = static function (string $source): array {
        preg_match_all('/^use ([\w\\\\]+)(?: as (\w+))?;$/m', $source, $uses, PREG_SET_ORDER);
        $imports = [];

        foreach ($uses as $use) {
            $imports[$use[2] ?? ArchChecks::lastSegment($use[1])] = $use[1];
        }

        preg_match_all('/->(?:scoped|singleton|bind)\(\s*(\w+)::class\s*(?:,\s*(\w+)::class\s*)?\)/', $source, $calls, PREG_SET_ORDER);

        return array_values(array_filter(
            array_map(static fn (array $call): string => $imports[$call[1]] ?? $call[1], $calls),
            static fn (string $class): bool => str_starts_with($class, 'App\\'),
        ));
    };
    $offenders = [];

    foreach (Globs::of($root . '/app' . '/Providers/*.php') as $file) {
        foreach ($bindings((string) file_get_contents($file)) as $class) {
            $offenders[] = basename($file) . ' binds ' . $class;
        }
    }

    expect($offenders)->toBe([])
        ->and($bindings("use App\\Modules\\Orders\\Services\\X;\n\$this->app->scoped(X::class);"))->toBe(['App\\Modules\\Orders\\Services\\X'])
        ->and($bindings("use App\\Modules\\Orders\\Contracts\\I;\nuse App\\Modules\\Orders\\Services\\C;\n\$this->app->bind(I::class, C::class);"))->toBe(['App\\Modules\\Orders\\Contracts\\I'])
        ->and($bindings("use Psr\\Clock\\ClockInterface;\nuse App\\Support\\SystemClock;\n\$this->app->singleton(ClockInterface::class, SystemClock::class);\n\$this->app->bind(App\\Modules\\Shipments\\Contracts\\LabelPrinter::class, static fn () => 1);"))->toBe([]);
});

test('listeners are found by discovery: no provider registers a listener of a module by hand', function (): void {
    $offenders = [];

    foreach (Globs::of(dirname(__DIR__, 2) . '/app/Providers/*.php') as $file) {
        if (preg_match('/^use App\\\\Modules\\\\\\w+\\\\Listeners\\\\/m', (string) file_get_contents($file)) === 1) {
            $offenders[] = basename($file);
        }
    }

    expect($offenders)->toBe([])
        ->and(file_get_contents(dirname(__DIR__, 2) . '/bootstrap' . '/app.php'))->toContain("->withEvents(discover: [app_path('Modules/*/Listeners')])");
});

test('the tenant middleware pair lives in one group', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap' . '/app.php');

    expect(substr_count($source, 'AbortIfNotTenantHost::class'))->toBe(1)
        ->and(substr_count($source, 'NeedsTenant::class'))->toBe(1)
        ->and($source)->toContain("\$middleware->group('tenant', [");
});
