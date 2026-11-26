<?php

declare(strict_types=1);

use Tests\Support\Architecture\InertiaPageGuard;
use Tests\Support\Architecture\PhpFileInspector;

uses()->group('arch');

it('detects an Inertia page without a file', function (string $source, string $reason): void {
    $violations = new InertiaPageGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'routes'], 'resources/js/Pages')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason)
        ->and($violations[0])->toContain('resources/js/Pages');
})->with([
    'a page that does not exist' => ["<?php\nuse Inertia\\Inertia;\nreturn Inertia::render('Order/DoesNotExist', []);\n", '3: the Inertia page Order/DoesNotExist has no file: add resources/js/Pages/Order/DoesNotExist.tsx'],
    'the helper' => ["<?php\nreturn inertia('Missing/Page');\n", '2: the Inertia page Missing/Page has no file'],
    'a page in a route default' => ["<?php\nRoute::get('/x', InertiaController::class)->defaults('component', 'MissingPage')->defaults('props', []);\n", '2: the Inertia page MissingPage has no file'],
    'a page named by a variable' => ["<?php\nuse Inertia\\Inertia;\nreturn Inertia::render(\$page);\n", '3: an Inertia page that is not named by a string'],
    'a page named by a built string' => ["<?php\nuse Inertia\\Inertia;\nreturn Inertia::render('Order/' . \$view, ['a' => 1]);\n", '3: an Inertia page that is not named by a string'],
]);

it('renders only Inertia pages that have a file', function (): void {
    $guard = new InertiaPageGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'routes'], 'resources/js/Pages');
    $accepted = "<?php\nuse Inertia\\Inertia;\nInertia::render('Dashboard');\nInertia::flash('success', 'x');\n\$view->render('Missing');\ninertia()->share('a', 1);\n\$route->defaults('props', []);\n";

    expect($guard->inSource($accepted))->toBe([])
        ->and($guard->violations())->toBe([]);
});
