<?php

declare(strict_types=1);

use Tests\Support\Architecture\LayoutGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

// The layout table of app/AGENTS.md, in the argument form of the guard (ModuleLayoutGuard reads the table from the guide itself).
$layout = static fn (string $root): LayoutGuard => new LayoutGuard(
    $root,
    [
        'app' => ['Casts', 'Http', 'Models', 'Modules', 'Policies', 'Providers', 'Rules', 'Support', 'View'],
        'app/Http' => ['Controllers', 'Middleware', 'Requests'],
        'app/Models' => ['Landlord', 'Pivots', 'Tenant'],
    ],
    'app/Modules',
    ['Actions', 'Commands', 'Contracts', 'Data', 'Enums', 'Events', 'Exceptions', 'Facades', 'Jobs', 'Listeners', 'Mail', 'Notifications', 'Services', 'Support', 'ValueObjects'],
    [
        'Commands' => ['Command'],
        'Data' => ['Data'],
        'Events' => ['Event'],
        'Exceptions' => ['Exception'],
        'Jobs' => ['Job'],
        'Mail' => ['Mail'],
        'Notifications' => ['Notification'],
        'Services' => ['Client', 'Service'],
    ],
    [
        'app/Http/Controllers' => ['Controller'],
        'app/Http/Requests' => ['Request'],
        'app/Policies' => ['Policy'],
        'app/Providers' => ['ServiceProvider', 'Provider'],
    ],
);

dataset('files that leave the layout', [
    'a folder outside the layers' => ['app/Helpers/Thing.php', 'place app/Helpers/Thing.php: move it to a folder of the layout table'],
    'a class in the root of app' => ['app/Stray.php', 'place app/Stray.php: move it'],
    'a folder outside the Http layer' => ['app/Http/Concerns/Thing.php', 'place app/Http/Concerns/Thing.php'],
    'a controller without the suffix, nested' => ['app/Http/Controllers/Order/Show.php', 'name app/Http/Controllers/Order/Show.php: end the class name in Controller'],
    'a class loose in the models root' => ['app/Models/Loose.php', 'place app/Models/Loose.php'],
    'a model folder outside the three' => ['app/Models/Traits/HasThing.php', 'place app/Models/Traits/HasThing.php'],
    'a class loose in a module' => ['app/Modules/Orders/Loose.php', 'place app/Modules/Orders/Loose.php'],
    'a folder a module may not hold' => ['app/Modules/Orders/Helpers/Thing.php', 'place app/Modules/Orders/Helpers/Thing.php'],
    'an event without the suffix' => ['app/Modules/Orders/Events/OrderCanceled.php', 'name app/Modules/Orders/Events/OrderCanceled.php: end the class name in Event'],
    'a service folder name that is neither of the two suffixes, nested' => ['app/Modules/Orders/Services/Nested/OrderRows.php', 'end the class name in Client or Service'],
]);

it('reports a folder, a root file or a name that leaves the layout, with the fix', function (string $file, string $reason) use ($layout): void {
    $root = SourceSandbox::create([$file => "<?php\n"]);

    try {
        $violations = $layout($root)->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason);
})->with('files that leave the layout');

dataset('files inside the layout and near misses', [
    'a controller in a nested folder' => ['app/Http/Controllers/Order/ShowController.php'],
    'a model of a tenant' => ['app/Models/Tenant/Order.php'],
    'a pivot' => ['app/Models/Pivots/OrderLink.php'],
    'an empty folder with a placeholder file' => ['app/Modules/Orders/Helpers/Empty/.gitkeep'],
    'a support class of a module' => ['app/Modules/Orders/Support/OrderNumber.php'],
    'a system file in the module root' => ['app/Modules/.DS_Store'],
    'an action, which has no suffix rule' => ['app/Modules/Orders/Actions/CancelOrder.php'],
    'an event with its suffix' => ['app/Modules/Orders/Events/OrderCanceledEvent.php'],
    'a client in a nested services folder' => ['app/Modules/Orders/Services/Nested/OrderClient.php'],
    'a provider with the long suffix' => ['app/Providers/AppServiceProvider.php'],
    'a provider with the short suffix' => ['app/Providers/RouteProvider.php'],
]);

it('accepts a class in its folder, a name with its suffix and files that are not classes', function (string $file) use ($layout): void {
    $root = SourceSandbox::create([$file => "<?php\n"]);

    try {
        expect($layout($root)->violations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('files inside the layout and near misses');

it('names the file relative to the root when the checkout sits under a dot directory', function () use ($layout): void {
    $root = SourceSandbox::create(['app/Stray.php' => "<?php\n", 'app/Modules/Orders/Events/OrderCanceledEvent.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = $layout($root)->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('place app/Stray.php');
});

it('keeps every class in the layout from app/AGENTS.md', function () use ($layout): void {
    $root = dirname(__DIR__, 2);

    expect(is_dir($root . '/app/Modules'))->toBeTrue()
        ->and($layout($root)->violations())->toBe([]);
});
