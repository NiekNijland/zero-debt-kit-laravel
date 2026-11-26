<?php

declare(strict_types=1);

use App\Models\Landlord\Tenant;
use App\Modules\Tenants\Support\TenantRowLock;
use Tests\Support\Architecture\ClassSourcePath;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TenantRowLockGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('locks of the tenant row', [
    'the model with a chained lock' => ["Tenant::query()\n    ->whereKey(\$id)\n    ->lockForUpdate()\n    ->firstOrFail();", 'lock it through TenantRowLock'],
    'the table with a lock mode' => ["DB::table('tenants')->where('id', \$id)->lock('for no key update')->first();", 'TenantRowLock'],
    'a shared lock' => ['Tenant::whereKey($id)->sharedLock()->first();', 'TenantRowLock'],
]);

it('detects a lock on the row of a tenant outside the tenant row lock', function (string $source, string $words): void {
    $violations = new TenantRowLockGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'x', 'Tenant', 'tenants', 'TenantRowLock')->inSource('<?php ' . $source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($words);
})->with('locks of the tenant row');

it('accepts a lock on another model and a read of the current tenant', function (): void {
    $guard = new TenantRowLockGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'x', 'Tenant', 'tenants', 'TenantRowLock');

    expect($guard->inSource("<?php\n\$tenant = Tenant::current();\n\$user = User::query()->lockForUpdate()->first();\n"))->toBe([])
        ->and($guard->inSource("<?php\nDB::table('orders')->lock('for update')->first();\n"))->toBe([]);
});

it('skips the file that owns the lock and reads a checkout under a dot directory', function (): void {
    $locked = "<?php\nTenant::query()->lockForUpdate()->first();\n";
    $root = SourceSandbox::create(['app/Owner.php' => $locked, 'app/Other.php' => $locked], prefix: '.checkout-');

    try {
        $violations = new TenantRowLockGuard(new PhpFileInspector(), $root, 'app', 'app/Owner.php', 'Tenant', 'tenants', 'TenantRowLock')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Other.php: the row of a tenant is locked directly');
});

it('locks the row of a tenant only in the tenant row lock', function (): void {
    $root = dirname(__DIR__, 2);

    expect(new TenantRowLockGuard(new PhpFileInspector(), $root, 'app', ClassSourcePath::of(TenantRowLock::class, $root), 'Tenant', 'tenants', 'TenantRowLock')->violations())->toBe([]);
});
