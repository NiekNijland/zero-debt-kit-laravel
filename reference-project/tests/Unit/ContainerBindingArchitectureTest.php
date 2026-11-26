<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Tests\Support\Architecture\ContainerBindingGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

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
