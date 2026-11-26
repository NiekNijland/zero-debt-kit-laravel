<?php

declare(strict_types=1);

use Tests\Support\Architecture\EloquentBuilderGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('builders and query-string packages', [
    'a custom builder method' => ['public function newEloquentBuilder($query)'],
    'the query-string package' => ['use Spatie\QueryBuilder\QueryBuilder;'],
    'the builder attribute' => ['#[UseEloquentBuilder(XBuilder::class)]'],
]);

it('detects a custom Eloquent builder and the query-string package', function (string $source): void {
    $violations = new EloquentBuilderGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'Spatie\QueryBuilder')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('write the query as a scope of the model');
})->with('builders and query-string packages');

it('accepts a scope and a package with a similar name', function (): void {
    $guard = new EloquentBuilderGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'Spatie\QueryBuilder');

    expect($guard->inSource('public function scopeActive($query) {} use Spatie\QueryBuilderHelper\X;'))->toBe([]);
});

it('writes queries as scopes of the model, with no custom builder and no query-string builder', function (): void {
    expect(new EloquentBuilderGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'Spatie\QueryBuilder')->violations())->toBe([]);
});

it('reads the app folder below a checkout under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create(['app/Models/A.php' => "<?php\nclass A { public function newEloquentBuilder(\$q) {} }\n"], prefix: '.checkout-');

    try {
        $builders = new EloquentBuilderGuard(new PhpFileInspector(), $root, 'app', 'Spatie\QueryBuilder')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($builders)->toHaveCount(1)
        ->and($builders[0])->toStartWith('app/Models/A.php: a custom Eloquent builder');
});
