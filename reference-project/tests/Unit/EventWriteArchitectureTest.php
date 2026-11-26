<?php

declare(strict_types=1);

use App\Models\Landlord\Event;
use App\Modules\Events\Services\AuditLogService;
use Tests\Support\Architecture\ClassSourcePath;
use Tests\Support\Architecture\EventWriteGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('event writes that fail', [
    'a raw delete' => ["<?php\nDB::statement('DELETE FROM events WHERE id = 1');\n", 'write it through AuditLogService'],
    'a query builder update' => ["<?php\nDB::table('events')\n    ->where('id', 1)\n    ->update(['a' => 1]);\n", 'AuditLogService'],
    'the model imported and written' => ["<?php\nuse App\\Models\\Landlord\\Event;\nEvent::query()->where('a', 1)->delete();\n", 'AuditLogService'],
    'the model imported under an alias' => ["<?php\nuse App\\Models\\Landlord\\Event as Row;\nRow::create([]);\n", 'AuditLogService'],
]);

it('detects a change or removal of an event outside the audit service', function (string $source, string $words): void {
    $guard = new EventWriteGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'], [], 'App\Models\Landlord\Event', 'events', '#^$#', 'AuditLogService');
    $violations = $guard->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($words);
})->with('event writes that fail');

it('accepts a read of events, another table and a class that only ends like the model', function (): void {
    $guard = new EventWriteGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'], [], 'App\Models\Landlord\Event', 'events', '#^$#', 'AuditLogService');
    $source = "<?php\nuse App\\Models\\Landlord\\Event;\nEvent::query()->where('a', 1)->get();\nDB::table('event_types')->update(['a' => 1]);\nDB::table('events')->get();\nOtherEvent::create([]);\n";

    expect($guard->inSource($source))->toBe([]);
});

it('lets only the audit log service, the pruning on the model and the migrations of the events table change or remove an event', function (): void {
    $root = dirname(__DIR__, 2);
    $allowed = [ClassSourcePath::of(AuditLogService::class, $root), ClassSourcePath::of(Event::class, $root)];
    $folders = ['app', 'routes', 'bootstrap', 'config', 'database/seeders', 'database/factories', 'database/migrations'];
    $guard = new EventWriteGuard(new PhpFileInspector(), $root, $folders, $allowed, Event::class, 'events', '#^database/migrations/.+_events(?:_table)?\.php$#', 'AuditLogService');

    expect($guard->violations())->toBe([]);
});

it('reads the folders below a checkout under a dot directory and skips the allowed file and the migrations of the table', function (): void {
    $root = SourceSandbox::create([
        'app/Owner.php' => "<?php\nDB::table('events')->delete();\n",
        'database/migrations/2024_01_01_000000_create_events_table.php' => "<?php\nDB::table('events')->update(['a' => 1]);\n",
        'database/migrations/2024_01_02_000000_create_orders_table.php' => "<?php\nDB::table('events')->update(['a' => 1]);\n",
    ], prefix: '.checkout-');

    try {
        $events = new EventWriteGuard(new PhpFileInspector(), $root, ['app', 'database/migrations'], ['app/Owner.php'], 'App\Models\Landlord\Event', 'events', '#_events(?:_table)?\.php$#', 'AuditLogService')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($events)->toHaveCount(1)
        ->and($events[0])->toStartWith('database/migrations/2024_01_02_000000_create_orders_table.php: ');
});
