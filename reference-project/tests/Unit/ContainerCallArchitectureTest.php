<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Tests\Support\Architecture\ContainerCallGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

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
