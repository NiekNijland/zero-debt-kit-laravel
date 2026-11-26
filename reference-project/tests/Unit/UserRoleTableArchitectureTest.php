<?php

declare(strict_types=1);

use App\Modules\Roles\Actions\AssignRoles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Support\Architecture\ClassSourcePath;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\UserRoleTableGuard;

uses()->group('arch');

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

it('lets only AssignRoles write to the role and permission tables of a user', function (): void {
    $root = dirname(__DIR__, 2);
    $allowed = [ClassSourcePath::of(AssignRoles::class, $root), ClassSourcePath::of(RolesAndPermissionsSeeder::class, $root)];
    $folders = ['app', 'routes', 'bootstrap', 'config', 'database/seeders', 'database/factories'];

    expect(new UserRoleTableGuard(new PhpFileInspector(), $root, $folders, $allowed, 'AssignRoles')->violations())->toBe([]);
});
