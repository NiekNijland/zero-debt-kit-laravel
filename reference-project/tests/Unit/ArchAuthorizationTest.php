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
use Tests\Support\Architecture\ClassSourcePath;
use Tests\Support\Architecture\EventWriteGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RoleAssignmentGuard;
use Tests\Support\Architecture\RoleNameGuard;
use Tests\Support\Architecture\UserRoleTableGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('role names that are checked', [
    'a role method' => ["<?php\n\$user->hasRole('admin');\n", 'check a permission with can()'],
    'a nullsafe role method' => ["<?php\n\$user?->hasAnyRole(['admin']);\n", 'check a permission with can()'],
    'a role middleware string' => ["<?php\nRoute::get('/x', X::class)->middleware('role:admin');\n", 'a permission middleware'],
    'a role or permission string in a list' => ["<?php\n\$m = ['auth|role_or_permission:admin'];\n", 'a permission middleware'],
]);

it('detects a role name that is checked in application code', function (string $source, string $words): void {
    $violations = new RoleNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($words);
})->with('role names that are checked');

dataset('role checks and near misses that pass', [
    'a permission check' => ["<?php\n\$user->can('orders.view');\n"],
    'a permission middleware' => ["<?php\nRoute::get('/x', X::class)->middleware('permission:orders.view');\n"],
    'a method that only starts like a role check' => ["<?php\n\$user->hasRoleLabel();\n"],
    'a text that mentions the word role' => ["<?php\n\$a = 'the role: admin is shown';\n"],
]);

it('accepts a permission check and the near misses of a role check', function (string $source): void {
    expect(new RoleNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->inSource($source))->toBe([]);
})->with('role checks and near misses that pass');

dataset('role writes that fail', [
    'assignRole' => ["<?php\n\$user->assignRole('admin');\n", 'call AssignRoles'],
    'syncRoles on a new line' => ["<?php\n\$user\n    ->syncRoles([]);\n", 'syncRoles'],
    'givePermissionTo' => ["<?php\n\$user->givePermissionTo('x');\n", 'givePermissionTo'],
]);

it('detects a role or permission change outside the owner', function (string $source, string $words): void {
    $violations = new RoleAssignmentGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app' => RoleAssignmentGuard::ALL], [], 'AssignRoles')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($words);
})->with('role writes that fail');

it('accepts a read of roles and, in a migration scope, a permission call that the scope leaves out', function (): void {
    $guard = new RoleAssignmentGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app' => RoleAssignmentGuard::ALL], [], 'AssignRoles');

    expect($guard->inSource("<?php\n\$user->getRoleNames();\n\$user->roles;\n\$assignRole = 1;\n"))->toBe([])
        ->and($guard->inSource("<?php\n\$user->givePermissionTo('x');\n", RoleAssignmentGuard::ROLES_ONLY))->toBe([]);
});

dataset('table writes that fail', [
    'a query builder insert' => ["<?php\nDB::table('model_has_roles')->insert(\$row);\n"],
    'a raw statement' => ["<?php\nDB::statement('DELETE FROM model_has_permissions WHERE 1');\n"],
    'a relation attach' => ["<?php\n\$user->roles()->attach(\$id);\n"],
    'a permission relation sync' => ["<?php\n\$user->permissions()->sync([]);\n"],
]);

it('detects a write to the role and permission tables outside the owner', function (string $source): void {
    $violations = new UserRoleTableGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'], [], 'AssignRoles')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('call AssignRoles');
})->with('table writes that fail');

it('accepts a read of the role tables and a relation that is not a role or permission', function (): void {
    $guard = new UserRoleTableGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'], [], 'AssignRoles');

    expect($guard->inSource("<?php\nDB::table('model_has_roles')->where('a', 1)->get();\n\$user->roles()->get();\n\$order->lines()->attach(1);\n"))->toBe([]);
});

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

it('reads the folders below a checkout under a dot directory and skips the allowed file and the migrations of the table', function (): void {
    $root = SourceSandbox::create([
        'app/Owner.php' => "<?php\n\$user->assignRole('a');\nDB::table('events')->delete();\n",
        'app/Other.php' => "<?php\n\$user->assignRole('a');\n",
        'database/migrations/2024_01_01_000000_create_events_table.php' => "<?php\nDB::table('events')->update(['a' => 1]);\n",
        'database/migrations/2024_01_02_000000_create_orders_table.php' => "<?php\nDB::table('events')->update(['a' => 1]);\n",
    ], prefix: '.checkout-');

    try {
        $assignments = new RoleAssignmentGuard(new PhpFileInspector(), $root, ['app' => RoleAssignmentGuard::ALL], ['app/Owner.php'], 'AssignRoles')->violations();
        $events = new EventWriteGuard(new PhpFileInspector(), $root, ['app', 'database/migrations'], ['app/Owner.php'], 'App\Models\Landlord\Event', 'events', '#_events(?:_table)?\.php$#', 'AuditLogService')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($assignments)->toHaveCount(1)
        ->and($assignments[0])->toStartWith('app/Other.php: ->assignRole()')
        ->and($events)->toHaveCount(1)
        ->and($events[0])->toStartWith('database/migrations/2024_01_02_000000_create_orders_table.php: ');
});

it('never checks a role name in application code', function (): void {
    expect(new RoleNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'routes', 'bootstrap', 'config'])->violations())->toBe([]);
});

arch('assigning roles never authorizes through the gate')
    ->expect(AssignRoles::class)
    ->not->toUse([Gate::class, GateContract::class]);

it('lets only AssignRoles change the roles and direct permissions of a user', function (): void {
    $root = dirname(__DIR__, 2);
    $allowed = [ClassSourcePath::of(AssignRoles::class, $root), ClassSourcePath::of(RolesAndPermissionsSeeder::class, $root)];
    $scopes = [
        'app' => RoleAssignmentGuard::ALL,
        'database/seeders' => RoleAssignmentGuard::ALL,
        'database/factories' => RoleAssignmentGuard::ALL,
        'database/migrations' => RoleAssignmentGuard::ROLES_ONLY,
        'routes' => RoleAssignmentGuard::ALL,
        'bootstrap' => RoleAssignmentGuard::ALL,
        'config' => RoleAssignmentGuard::ALL,
    ];

    expect(new RoleAssignmentGuard(new PhpFileInspector(), $root, $scopes, $allowed, 'AssignRoles')->violations())->toBe([]);
});

it('lets only AssignRoles write to the role and permission tables of a user', function (): void {
    $root = dirname(__DIR__, 2);
    $allowed = [ClassSourcePath::of(AssignRoles::class, $root), ClassSourcePath::of(RolesAndPermissionsSeeder::class, $root)];
    $folders = ['app', 'routes', 'bootstrap', 'config', 'database/seeders', 'database/factories'];

    expect(new UserRoleTableGuard(new PhpFileInspector(), $root, $folders, $allowed, 'AssignRoles')->violations())->toBe([]);
});

arch('requests that check data against a tenant take the tenant from the route')
    ->expect([
        StoreUserRequest::class,
        StoreProductRequest::class,
    ])
    ->not->toUse(Tenant::class);

it('lets only the audit log service, the pruning on the model and the migrations of the events table change or remove an event', function (): void {
    $root = dirname(__DIR__, 2);
    $allowed = [ClassSourcePath::of(AuditLogService::class, $root), ClassSourcePath::of(Event::class, $root)];
    $folders = ['app', 'routes', 'bootstrap', 'config', 'database/seeders', 'database/factories', 'database/migrations'];
    $guard = new EventWriteGuard(new PhpFileInspector(), $root, $folders, $allowed, Event::class, 'events', '#^database/migrations/.+_events(?:_table)?\.php$#', 'AuditLogService');

    expect($guard->violations())->toBe([]);
});
