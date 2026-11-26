<?php

declare(strict_types=1);

use Tests\Support\Architecture\ImportSpellingGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('imports in the wrong spelling', [
    'a controller by its own import' => ["<?php\nuse App\\Http\\Controllers\\Order\\ShowController;\n", 'import the folder and write Folder\Noun\VerbController::class'],
    'a controller under an alias' => ["<?php\nuse App\\Http\\Controllers\\Order\\ShowController as Show;\n", 'a route file imports a controller'],
]);

it('detects a controller import in a route file', function (string $source, string $words): void {
    $violations = new ImportSpellingGuard(dirname(__DIR__, 2), 'App\Http\Controllers')->controllerImportInSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($words);
})->with('imports in the wrong spelling');

it('accepts the folder import and an import of something that is not a controller', function (): void {
    $guard = new ImportSpellingGuard(dirname(__DIR__, 2), 'App\Http\Controllers');

    expect($guard->controllerImportInSource("<?php\nuse App\\Http\\Controllers\\Order;\nuse App\\Http\\Requests\\StoreRequest;\n"))->toBe([]);
});

it('reads the named files and fails on a file that does not exist', function (): void {
    $root = SourceSandbox::create(['routes/api.php' => "<?php\nuse App\\Http\\Controllers\\Order\\ShowController;\n", 'routes/web.php' => "<?php\nuse App\\Http\\Controllers\\Order;\n"], prefix: '.checkout-');

    try {
        $guard = new ImportSpellingGuard($root, 'App\Http\Controllers');
        $violations = $guard->violations($guard->files('routes/*.php'));
        $missing = fn (): array => $guard->violations(['routes/renamed.php']);
        expect($missing)->toThrow(RuntimeException::class, 'does not exist');
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('routes/api.php: a route file imports a controller');
});

it('imports a controller folder in the route files and the bootstrap file', function (): void {
    $guard = new ImportSpellingGuard(dirname(__DIR__, 2), 'App\Http\Controllers');

    expect($guard->violations([...$guard->files('routes/*.php'), 'bootstrap/app.php']))->toBe([]);
});
