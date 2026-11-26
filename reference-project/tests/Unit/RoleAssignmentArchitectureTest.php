<?php

declare(strict_types=1);

use App\Modules\Roles\Actions\AssignRoles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Support\Architecture\ClassSourcePath;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RoleAssignmentGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

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

it('reads the folders below a checkout under a dot directory and skips the allowed file', function (): void {
    $root = SourceSandbox::create([
        'app/Owner.php' => "<?php\n\$user->assignRole('a');\n",
        'app/Other.php' => "<?php\n\$user->assignRole('a');\n",
    ], prefix: '.checkout-');

    try {
        $assignments = new RoleAssignmentGuard(new PhpFileInspector(), $root, ['app' => RoleAssignmentGuard::ALL], ['app/Owner.php'], 'AssignRoles')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($assignments)->toHaveCount(1)
        ->and($assignments[0])->toStartWith('app/Other.php: ->assignRole()');
});
