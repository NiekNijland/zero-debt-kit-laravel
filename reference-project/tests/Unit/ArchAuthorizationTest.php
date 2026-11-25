<?php

declare(strict_types=1);

use App\Http\Requests\Admin\Products\StoreProductRequest;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Models\Landlord\Event;
use App\Models\Landlord\Tenant;
use App\Modules\Events\Services\AuditLogService;
use App\Modules\Roles\Actions\AssignRoles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Gate;
use Tests\Support\ArchChecks;
use Tests\Support\Architecture\PhpFileInspector;

uses()->group('arch');

test('role names are never checked in application code', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $offenders = [];

    foreach (['app', 'routes', 'bootstrap', 'config'] as $directory) {
        foreach ($inspector->phpFiles($root . '/' . $directory) as $file) {
            if (ArchChecks::checksRoleName($inspector, $file)) {
                $offenders[] = substr($file, strlen($root) + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});

arch('assigning roles never authorizes through the gate')
    ->expect(AssignRoles::class)
    ->not->toUse([Gate::class, GateContract::class]);

test('only AssignRoles changes the roles and direct permissions of a user', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $writtenDirectly = [ArchChecks::sourceOf(RolesAndPermissionsSeeder::class)];
    $everything = 'assignRole|syncRoles|removeRole|givePermissionTo|revokePermissionTo';
    $scopes = [
        'app' => [$everything, []],
        'database/seeders' => [$everything, []],
        'database/factories' => [$everything, []],
        'database/migrations' => ['assignRole|syncRoles|removeRole', []],
        'routes' => [$everything, []],
        'bootstrap' => [$everything, []],
        'config' => [$everything, []],
    ];
    $offenders = [];

    foreach ($scopes as $directory => [$methods, $allowed]) {
        foreach ($inspector->phpFiles($root . '/' . $directory) as $file) {
            $path = substr($file, strlen($root) + 1);

            if ($path === ArchChecks::sourceOf(AssignRoles::class) || in_array($path, [...$writtenDirectly, ...$allowed], true)) {
                continue;
            }

            if (preg_match('/->(' . $methods . ')\s*\(/', (string) file_get_contents($file)) === 1) {
                $offenders[] = $path;
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('only AssignRoles writes to the role and permission tables of a user', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $allowed = [
        ArchChecks::sourceOf(AssignRoles::class),
        ArchChecks::sourceOf(RolesAndPermissionsSeeder::class),
    ];
    $patterns = [
        '/model_has_(?:roles|permissions)[^;]*->(?:insert|insertOrIgnore|insertGetId|upsert|update|delete|truncate)\s*\(/s',
        '/(?:INSERT\s+INTO|DELETE\s+FROM|UPDATE)\s+model_has_(?:roles|permissions)/i',
        '/->(?:roles|permissions)\(\)->(?:attach|sync|detach|toggle)\s*\(/',
    ];
    $offenders = [];

    foreach (['app', 'routes', 'bootstrap', 'config', 'database/seeders', 'database/factories'] as $directory) {
        foreach ($inspector->phpFiles($root . '/' . $directory) as $file) {
            $path = substr($file, strlen($root) + 1);

            if (in_array($path, $allowed, true)) {
                continue;
            }

            $source = (string) file_get_contents($file);

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $source) === 1) {
                    $offenders[] = $path;

                    break;
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});

arch('requests that check data against a tenant take the tenant from the route')
    ->expect([
        StoreUserRequest::class,
        StoreProductRequest::class,
    ])
    ->not->toUse(Tenant::class);

test('only the audit log service, the pruning on the model and the migrations of the events table change or remove an event', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $allowed = [
        ArchChecks::sourceOf(AuditLogService::class),
        ArchChecks::sourceOf(Event::class),
    ];
    $writes = 'create|forceCreate|firstOrCreate|updateOrCreate|insert|insertOrIgnore|insertGetId|insertUsing|upsert|update|updateOrInsert|increment|decrement|delete|forceDelete|truncate|prune|massPrune';
    $offenders = [];

    foreach (['app', 'routes', 'bootstrap', 'config', 'database/seeders', 'database/factories', 'database/migrations'] as $directory) {
        foreach ($inspector->phpFiles($root . '/' . $directory) as $file) {
            $path = substr($file, strlen($root) + 1);

            // Migrations rewrite the names of events when an event class moves; a migration has no class, so only its name says what it is about.
            if (in_array($path, $allowed, true) || preg_match('#^database/migrations/.+_events(?:_table)?\.php$#', $path) === 1) {
                continue;
            }

            $source = (string) file_get_contents($file);
            $patterns = [
                '/\b(?:UPDATE|DELETE\s+FROM|INSERT\s+INTO|TRUNCATE(?:\s+TABLE)?)\s+events\b/i',
                '/DB::table\(\s*[\'"]events[\'"]\s*\)[^;]*->\s*(?:' . $writes . ')\s*\(/s',
            ];

            if (preg_match_all('/^use\s+App\\\\Models\\\\Landlord\\\\Event(?:\s+as\s+(\w+))?;/m', $source, $imports, PREG_SET_ORDER) > 0) {
                foreach ($imports as $import) {
                    $patterns[] = '/\b' . ($import[1] ?? 'Event') . '::[^;]*->\s*(?:' . $writes . ')\s*\(/s';
                    $patterns[] = '/\b' . ($import[1] ?? 'Event') . '::(?:' . $writes . ')\s*\(/';
                }
            }

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $source) === 1) {
                    $offenders[] = $path;

                    break;
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});
