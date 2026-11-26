<?php

declare(strict_types=1);

use App\Support\RouteMatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Tests\Support\Architecture\BootstrapRegistrationGuard;
use Tests\Support\Architecture\ContainerBindingGuard;
use Tests\Support\Architecture\ContainerCallGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RouteFileLoadGuard;
use Tests\Support\Architecture\ScheduledOneServerGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

arch('routes are registered only in routes/ and bootstrap/app.php')
    ->expect([Route::class, Router::class])
    ->toOnlyBeUsedIn([RouteMatcher::class, 'Tests']);

dataset('route file loads', [
    'in a provider method' => ["<?php\nfinal class P { public function boot(): void { \$this->loadRoutesFrom(__DIR__ . '/x.php'); } }\n"],
    'on a held object' => ["<?php\n\$provider->loadRoutesFrom(\$path);\n"],
]);

it('detects a class that loads a route file', function (string $source): void {
    $violations = new RouteFileLoadGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('register routes in the route files and in the bootstrap file');
})->with('route file loads');

it('accepts a class that mentions the call in a string or a comment, or loads something else', function (): void {
    $guard = new RouteFileLoadGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app');

    expect($guard->inSource("<?php\n// loadRoutesFrom(\$a)\n\$a = 'loadRoutesFrom';\n\$this->loadMigrationsFrom(\$path);\n"))->toBe([]);
});

it('reads the folder below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/P.php' => "<?php\n\$this->loadRoutesFrom(\$p);\n", 'app/Q.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new RouteFileLoadGuard(new PhpFileInspector(), $root, 'app')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/P.php: line 2: a class loads a route file');
});

it('loads no route file from a class under app/', function (): void {
    expect(new RouteFileLoadGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});

dataset('container calls', [
    'resolve()' => ['<?php $a = resolve(Foo::class);', 1],
    'app() and a global app()' => ['<?php $a = app(Foo::class); $b = \app();', 2],
    'the facade make and a qualified call' => ['<?php $a = App::make(Foo::class); $b = \Illuminate\Support\Facades\App::call($x);', 2],
    'a held container' => ['<?php $a = app()->make(Foo::class); $b = $this->container->make(Foo::class); $c = $container->make(Foo::class);', 3],
    'the container instance' => ['<?php $a = Container::getInstance();', 1],
    'in a trait' => ['<?php trait T { public function f(): void { resolve(Foo::class); } }', 1],
    'in the failed hook of a job' => ['<?php final class J { public function failed(): void { app(Foo::class)->handle(); } }', 1],
    'in a closure of a boot hook' => ['<?php final class M extends Model { protected static function booted(): void { static::saved(static fn () => resolve(Foo::class)->forget()); } }', 1],
]);

it('detects a dependency that comes through the container', function (string $source, int $count): void {
    $violations = new ContainerCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', [], App::class)->inSource($source);

    expect($violations)->toHaveCount($count)
        ->and($violations[0])->toContain('take the dependency as a constructor argument, or as an argument of handle()');
})->with('container calls');

it('names the method of a container call and accepts a facade method that is not a call into the container', function (): void {
    $guard = new ContainerCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', [], App::class);

    expect($guard->inSource('<?php final class J { public function failed(): void { app(Foo::class); } }')[0])->toStartWith('failed line 1: ')
        ->and($guard->inSource('<?php $a = app();')[0])->toStartWith('outside a method line 1: ')
        ->and($guard->inSource('<?php $a = App::environment(); $b = App::getLocale(); $c = Hash::make($x); $d = $this->resolve($x); $e = $factory->make();'))->toBe([]);
});

it('skips the folders that wire the container and reads a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/Providers/P.php' => "<?php\n\$a = app(X::class);\n", 'app/Service.php' => "<?php\n\$a = app(X::class);\n"], prefix: '.checkout-');

    try {
        $violations = new ContainerCallGuard(new PhpFileInspector(), $root, 'app', ['app/Providers/'], App::class)->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Service.php: outside a method line 2');
});

it('takes every dependency through the constructor or handle(), never through the container', function (): void {
    expect(new ContainerCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', ['app/Providers/'], App::class)->violations())->toBe([]);
});

dataset('scheduled tasks that fail', [
    'a command without one server' => ['<?php Schedule::command("a")->daily();', 'line 1: a scheduled task does not run on one server: end it with ->onOneServer()'],
    'a closure' => ['<?php Schedule::call(static function (): void { run(); })->hourly()->onOneServer();', 'a closure is scheduled: make it a command'],
    'the second of two' => ['<?php Schedule::command("a")->onOneServer(); Schedule::command("b")->daily();', 'does not run on one server'],
]);

it('detects a scheduled task that runs on every server and a closure', function (string $source, string $words): void {
    $violations = new ScheduledOneServerGuard(dirname(__DIR__, 2), 'routes')->inSource($source);

    expect($violations)->not->toBe([])
        ->and($violations[0])->toContain($words);
})->with('scheduled tasks that fail');

it('accepts a task that ends in onOneServer, in any place of the chain', function (): void {
    $guard = new ScheduledOneServerGuard(dirname(__DIR__, 2), 'routes');

    expect($guard->inSource('<?php Schedule::command("b")->onOneServer()->daily();'))->toBe([])
        ->and($guard->inSource('<?php Schedule::command("b")->daily()->onOneServer();'))->toBe([])
        ->and($guard->inSource('<?php $schedule->call(fn () => 1); Other::call(fn () => 2);'))->toBe([]);
});

it('reads the route files below a checkout under a dot directory and fails on a folder with no task', function (): void {
    $root = SourceSandbox::create(['routes/console.php' => "<?php\nSchedule::command('a')->daily();\n", 'empty/x.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new ScheduledOneServerGuard($root, 'routes')->violations();
        $empty = new ScheduledOneServerGuard($root, 'empty')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('routes/console.php: line 2: a scheduled task does not run on one server')
        ->and($empty)->toHaveCount(1)
        ->and($empty[0])->toContain('no scheduled task was read');
});

it('runs every scheduled task as a command on one server', function (): void {
    expect(new ScheduledOneServerGuard(dirname(__DIR__, 2), 'routes')->violations())->toBe([]);
});

dataset('bindings that a class declares itself', [
    'a scoped class of the application' => ["use App\\Modules\\Orders\\Services\\X;\n\$this->app->scoped(X::class);", 'App\\Modules\\Orders\\Services\\X'],
    'an interface of the application bound to a class' => ["use App\\Modules\\Orders\\Contracts\\I;\nuse App\\Modules\\Orders\\Services\\C;\n\$this->app->bind(I::class, C::class);", 'App\\Modules\\Orders\\Contracts\\I'],
    'a singleton under an alias' => ["use App\\Support\\Clock as AppClock;\n\$this->app->singleton(AppClock::class);", 'App\\Support\\Clock'],
]);

it('detects a binding of an application class in a provider', function (string $source, string $class): void {
    $violations = new ContainerBindingGuard(dirname(__DIR__, 2), 'app/Providers', 'App\\')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('binds ' . $class . ' in a provider', '#[Scoped], #[Singleton] or #[Bind]');
})->with('bindings that a class declares itself');

it('accepts the binding of a vendor class and of a closure', function (): void {
    $guard = new ContainerBindingGuard(dirname(__DIR__, 2), 'app/Providers', 'App\\');
    $source = "use Psr\\Clock\\ClockInterface;\nuse App\\Support\\SystemClock;\n\$this->app->singleton(ClockInterface::class, SystemClock::class);\n\$this->app->bind(App\\Modules\\Shipments\\Contracts\\LabelPrinter::class, static fn () => 1);";

    expect($guard->inSource($source))->toBe([]);
});

it('reads the providers below a checkout under a dot directory and fails on a folder without one', function (): void {
    $root = SourceSandbox::create(['providers/P.php' => "<?php\nuse App\\X;\n\$this->app->scoped(X::class);\n", 'empty/.gitkeep' => ''], prefix: '.checkout-');

    try {
        $violations = new ContainerBindingGuard($root, 'providers', 'App\\')->violations();
        $empty = new ContainerBindingGuard($root, 'empty', 'App\\')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('providers/P.php: binds App\\X in a provider')
        ->and($empty[0])->toContain('no provider was read');
});

it('lets a class of the application declare its own binding, not a provider', function (): void {
    expect(new ContainerBindingGuard(dirname(__DIR__, 2), 'app/Providers', 'App\\')->violations())->toBe([]);
});

dataset('registrations that fail', [
    'a listener imported in a provider' => ['provider', "<?php\nuse App\\Modules\\Orders\\Listeners\\SendMail;\n", 'listeners are found by discovery'],
    'a bootstrap file without discovery' => ['bootstrap', "<?php\n\$middleware->group('tenant', [A::class, B::class]);\n", 'the discovery of listeners is gone'],
    'the pair named twice' => ['bootstrap', "<?php\n->withEvents(discover: [x])\n\$middleware->group('tenant', [A::class, B::class]);\n\$middleware->append(A::class);\n", 'A::class is named 2 times'],
    'the pair in no group' => ['bootstrap', "<?php\n->withEvents(discover: [x])\n\$middleware->append(A::class);\n\$middleware->append(B::class);\n", 'the group tenant is gone'],
]);

it('detects a listener registered by hand and a tenant middleware pair that left its group', function (string $kind, string $source, string $words): void {
    $guard = new BootstrapRegistrationGuard(dirname(__DIR__, 2), 'app/Providers', 'bootstrap/app.php', 'App\\\\Modules\\\\\w+\\\\Listeners\\\\', '->withEvents(discover: [x])', ['A::class', 'B::class'], 'tenant');
    $violations = $kind === 'provider' ? $guard->providerInSource($source) : $guard->bootstrapInSource($source);

    expect($violations)->not->toBe([])
        ->and(implode("\n", $violations))->toContain($words);
})->with('registrations that fail');

it('accepts a provider that imports no listener and a bootstrap file with discovery and the pair once in one group', function (): void {
    $guard = new BootstrapRegistrationGuard(dirname(__DIR__, 2), 'app/Providers', 'bootstrap/app.php', 'App\\\\Modules\\\\\w+\\\\Listeners\\\\', '->withEvents(discover: [x])', ['A::class', 'B::class'], 'tenant');

    expect($guard->providerInSource("<?php\nuse App\\Modules\\Orders\\Models\\Order;\nuse App\\Listeners\\Other;\n"))->toBe([])
        ->and($guard->bootstrapInSource("<?php\n->withEvents(discover: [x])\n\$middleware->group('tenant', [A::class, B::class]);\n"))->toBe([]);
});

it('reads the providers and the bootstrap file below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['providers/P.php' => "<?php\nuse App\\Modules\\Orders\\Listeners\\L;\n", 'boot/app.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new BootstrapRegistrationGuard($root, 'providers', 'boot/app.php', 'App\\\\Modules\\\\\w+\\\\Listeners\\\\', 'D', ['A::class'], 'tenant')->violations();
        $missing = new BootstrapRegistrationGuard($root, 'providers', 'boot/none.php', 'X', 'D', [], 'tenant')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations[0])->toStartWith('providers/P.php: a provider imports a listener of a module')
        ->and($violations)->toContain('boot/app.php: the discovery of listeners is gone: add D, or a listener is never registered')
        ->and($missing[count($missing) - 1])->toContain('the bootstrap file does not exist');
});

it('finds listeners by discovery and keeps the tenant middleware pair in one group', function (): void {
    $guard = new BootstrapRegistrationGuard(dirname(__DIR__, 2), 'app/Providers', 'bootstrap/app.php', 'App\\\\Modules\\\\\w+\\\\Listeners\\\\', "->withEvents(discover: [app_path('Modules/*/Listeners')])", ['AbortIfNotTenantHost::class', 'NeedsTenant::class'], 'tenant');

    expect($guard->violations())->toBe([]);
});
