<?php

declare(strict_types=1);

use Inertia\Response;
use Tests\Support\Architecture\InertiaImportGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('Inertia response imports in the wrong spelling', [
    'the Inertia response as Response' => ["<?php\nuse Inertia\\Response;\n"],
    'the Inertia response under another alias' => ["<?php\nuse Inertia\\Response as Page;\n"],
]);

it('detects the Inertia response under another name', function (string $source): void {
    $violations = new InertiaImportGuard(new PhpFileInspector(), dirname(__DIR__, 2), Response::class, 'InertiaResponse')->pageImportInSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('import it as InertiaResponse');
})->with('Inertia response imports in the wrong spelling');

it('accepts the Inertia response as InertiaResponse and the HTTP response', function (): void {
    $guard = new InertiaImportGuard(new PhpFileInspector(), dirname(__DIR__, 2), Response::class, 'InertiaResponse');

    expect($guard->pageImportInSource("<?php\nuse Inertia\\Response as InertiaResponse;\nuse Illuminate\\Http\\Response;\n"))->toBe([]);
});

it('reads the folders below a checkout under a dot directory and fails on a folder that does not exist', function (): void {
    $root = SourceSandbox::create(['app/A.php' => "<?php\nuse Inertia\\Response;\n", 'app/B.php' => "<?php\nuse Inertia\\Response as InertiaResponse;\n"], prefix: '.checkout-');

    try {
        $guard = new InertiaImportGuard(new PhpFileInspector(), $root, Response::class, 'InertiaResponse');
        $violations = $guard->violations(['app']);
        $missing = fn (): array => $guard->violations(['renamed']);
        expect($missing)->toThrow(InvalidArgumentException::class, 'does not exist');
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/A.php: the Inertia response is imported under another name');
});

it('imports the Inertia response as InertiaResponse', function (): void {
    expect(new InertiaImportGuard(new PhpFileInspector(), dirname(__DIR__, 2), Response::class, 'InertiaResponse')->violations(['app']))->toBe([]);
});
