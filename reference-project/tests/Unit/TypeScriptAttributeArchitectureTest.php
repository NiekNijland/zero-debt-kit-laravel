<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TypeScriptAttributeGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('TypeScript attributes that rename the type', [
    'a name' => ["<?php\n#[TypeScript('Other')]\nfinal class A {}\n"],
    'a named argument' => ["<?php\n#[\\Spatie\\TypeScriptTransformer\\Attributes\\TypeScript(name: 'Other')]\nfinal class A {}\n"],
]);

it('detects a TypeScript attribute that renames the type', function (string $source): void {
    $violations = new TypeScriptAttributeGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'TypeScript')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('drop the argument, the TypeScript type is named after the PHP class');
})->with('TypeScript attributes that rename the type');

it('accepts the bare attribute and another attribute with an argument', function (): void {
    $guard = new TypeScriptAttributeGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'TypeScript');
    $source = "<?php\n#[TypeScript]\nfinal class A {}\n#[Other('x')]\nfinal class B {}\n";

    expect($guard->inSource($source))->toBe([])
        ->and($guard->attributeCount($source))->toBe(1);
});

it('reads the attributes below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/A.php' => "<?php\n#[TypeScript('X')]\nfinal class A {}\n"], prefix: '.checkout-');

    try {
        $violations = new TypeScriptAttributeGuard(new PhpFileInspector(), $root, 'app', 'TypeScript')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/A.php: line 2: #[TypeScript] renames the type');
});

it('names every TypeScript type after its PHP class', function (): void {
    expect(new TypeScriptAttributeGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'TypeScript')->violations())->toBe([]);
});
