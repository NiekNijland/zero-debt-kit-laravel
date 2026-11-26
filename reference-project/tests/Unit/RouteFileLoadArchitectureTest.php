<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RouteFileLoadGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

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
