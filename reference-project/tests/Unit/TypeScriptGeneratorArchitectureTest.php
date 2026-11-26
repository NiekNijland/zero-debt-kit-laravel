<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use Tests\Support\Architecture\ClassSourcePath;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TypeScriptGeneratorGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('uses of the TypeScript generator in runtime code', [
    'the transformer package' => ["<?php\nuse Spatie\\TypeScriptTransformer\\TypeScriptTransformerConfig;\n"],
    'the Laravel bridge' => ["<?php\nuse Spatie\\LaravelTypeScriptTransformer\\Transformers\\X;\n"],
    'a qualified name in the code' => ["<?php\nnamespace App;\nfinal class A { public function b(): void { new \\Spatie\\TypeScriptTransformer\\Collectors\\Collector(); } }\n"],
]);

it('detects the dev-only TypeScript generator in runtime code', function (string $source): void {
    $guard = new TypeScriptGeneratorGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', [], [], ['Spatie\\LaravelTypeScriptTransformer\\', 'Spatie\\TypeScriptTransformer\\'], 'Spatie\\TypeScriptTransformer\\Attributes\\');
    $violations = $guard->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('only its provider and its own folder name it');
})->with('uses of the TypeScript generator in runtime code');

it('accepts the TypeScript attribute and a package with a similar name', function (): void {
    $guard = new TypeScriptGeneratorGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', [], [], ['Spatie\\LaravelTypeScriptTransformer\\', 'Spatie\\TypeScriptTransformer\\'], 'Spatie\\TypeScriptTransformer\\Attributes\\');

    expect($guard->inSource("<?php\nuse Spatie\\TypeScriptTransformer\\Attributes\\TypeScript;\nuse Spatie\\TypeScriptTransformerHelpers\\X;\n#[TypeScript]\nfinal class A {}\n"))->toBe([]);
});

it('reads the folder under a dot directory and skips the files that configure the generator', function (): void {
    $source = "<?php\nuse Spatie\\TypeScriptTransformer\\TypeScriptTransformerConfig;\n";
    $root = SourceSandbox::create(['app/Providers/Gen.php' => $source, 'app/Support/TypeScript/Own.php' => $source, 'app/Http/Bad.php' => $source], prefix: '.checkout-');

    try {
        $violations = new TypeScriptGeneratorGuard(new PhpFileInspector(), $root, 'app', ['app/Providers/Gen.php'], ['app/Support/TypeScript/'], ['Spatie\\LaravelTypeScriptTransformer\\', 'Spatie\\TypeScriptTransformer\\'], 'Spatie\\TypeScriptTransformer\\Attributes\\')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Http/Bad.php: the dev-only TypeScript generator');
});

it('keeps the dev-only TypeScript generator out of runtime code', function (): void {
    $root = dirname(__DIR__, 2);
    $allowed = [ClassSourcePath::of(AppServiceProvider::class, $root), ClassSourcePath::of(TypeScriptTransformerServiceProvider::class, $root)];
    $guard = new TypeScriptGeneratorGuard(new PhpFileInspector(), $root, 'app', $allowed, ['app/Support/TypeScript/'], ['Spatie\\LaravelTypeScriptTransformer\\', 'Spatie\\TypeScriptTransformer\\'], 'Spatie\\TypeScriptTransformer\\Attributes\\');

    expect($guard->violations())->toBe([]);
});
