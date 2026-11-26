<?php

declare(strict_types=1);

use Tests\Support\Architecture\LayoutGuard;
use Tests\Support\InstancesOf;

uses()->group('arch');

it('keeps every class in the layout from app/AGENTS.md', function (): void {
    expect(new LayoutGuard(dirname(__DIR__, 2))->violations())->toBe([]);
});

it('reports folders, root files and names that leave the layout', function (): void {
    $root = sys_get_temp_dir() . '/layout-guard-' . bin2hex(random_bytes(6));
    $files = [
        'app/Helpers/Thing.php',
        'app/Stray.php',
        'app/Http/Controllers/Order/ShowController.php',
        'app/Http/Controllers/Order/Show.php',
        'app/Http/Concerns/Thing.php',
        'app/Models/Tenant/Order.php',
        'app/Models/Pivots/OrderLink.php',
        'app/Models/Traits/HasThing.php',
        'app/Models/Loose.php',
        'app/Modules/Orders/Loose.php',
        'app/Modules/Orders/Helpers/Thing.php',
        'app/Modules/Orders/Helpers/Empty/.gitkeep',
        'app/Modules/Orders/Support/OrderNumber.php',
        'app/Modules/.DS_Store',
        'app/Modules/Orders/Actions/CancelOrder.php',
        'app/Modules/Orders/Events/OrderCanceledEvent.php',
        'app/Modules/Orders/Events/OrderCanceled.php',
        'app/Modules/Orders/Services/Nested/OrderClient.php',
        'app/Modules/Orders/Services/Nested/OrderRows.php',
    ];

    foreach ($files as $file) {
        if (! is_dir(dirname($root . '/' . $file))) {
            mkdir(dirname($root . '/' . $file), 0777, true);
        }

        file_put_contents($root . '/' . $file, "<?php\n");
    }

    try {
        expect(new LayoutGuard($root)->violations())->toBe([
            'name app/Http/Controllers/Order/Show.php',
            'name app/Modules/Orders/Events/OrderCanceled.php',
            'name app/Modules/Orders/Services/Nested/OrderRows.php',
            'place app/Helpers/Thing.php',
            'place app/Http/Concerns/Thing.php',
            'place app/Modules/Orders/Helpers/Thing.php',
            'place app/Modules/Orders/Loose.php',
            'place app/Models/Loose.php',
            'place app/Models/Traits/HasThing.php',
            'place app/Stray.php',
        ]);
    } finally {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry = InstancesOf::one($entry, SplFileInfo::class);
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($root);
    }
});
