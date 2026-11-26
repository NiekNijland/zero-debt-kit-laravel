<?php

declare(strict_types=1);

use App\Modules\Tenants\Support\QueriesAcrossTenants;
use Tests\Support\Architecture\ClassSourcePath;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TenantScopeBypassGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('scope bypasses and raw queries of a modeled table', [
    'a removed scope and a global call' => ['<?php $q->withoutGlobalScope(TenantScope::class)->acrossTenants(); User::withoutGlobalScopes();', 2, 'removes the tenant scope'],
    'a scope bypass on a new query' => ['<?php $m->newQueryWithoutScopes();', 1, 'acrossTenants()'],
    'the raw table of a model' => ['<?php $q = DB::table("order_lines");', 1, 'query the model, so its scopes apply'],
    'the qualified facade with an alias' => ['<?php $r = \\Illuminate\\Support\\Facades\\DB::table(\'orders as o\');', 1, "DB::table('orders')"],
]);

it('detects a removed tenant scope and a raw query of a table that has a model', function (string $source, int $count, string $words): void {
    $guard = new TenantScopeBypassGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'app/Models', 'x', 'QueriesAcrossTenants');
    $violations = $guard->inSource($source, ['users', 'orders', 'order_lines', 'category_product']);

    expect($violations)->toHaveCount($count)
        ->and($violations[0])->toContain($words);
})->with('scope bypasses and raw queries of a modeled table');

it('accepts a table without a model, a table held in a variable, the scope-aware read and a method that only ends like a bypass', function (): void {
    $guard = new TenantScopeBypassGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'app/Models', 'x', 'QueriesAcrossTenants');

    expect($guard->inSource('<?php $q = DB::table("migrations"); $r = DB::table($table); $s = User::query()->acrossTenants(); $t = $x->withoutGlobalScopesHelper;', ['users']))->toBe([]);
});

it('reads the tables of the models from the folder, the declared table first and the plural of the class name otherwise, and skips an abstract class', function (): void {
    $root = SourceSandbox::create([
        'app/Models/CategoryProduct.php' => "<?php\nfinal class CategoryProduct {}\n",
        'app/Models/Declared.php' => "<?php\nfinal class Declared { protected \$table = 'legacy_rows'; }\n",
        'app/Models/Base.php' => "<?php\nabstract class Base {}\n",
    ], prefix: '.checkout-');

    try {
        $tables = new TenantScopeBypassGuard(new PhpFileInspector(), $root, 'app', 'app/Models', 'x', 'QueriesAcrossTenants')->tables();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($tables)->toBe(['category_products', 'legacy_rows']);
});

it('names a stale exemption and the files that remove a scope or query a modeled table, in a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create([
        'app/Models/User.php' => "<?php\nfinal class User {}\n",
        'app/Trait.php' => "<?php\n\$a = 1;\n",
        'app/Service.php' => "<?php\nDB::table('users')->get();\n",
    ], prefix: '.checkout-');

    try {
        $violations = new TenantScopeBypassGuard(new PhpFileInspector(), $root, 'app', 'app/Models', 'app/Trait.php', 'QueriesAcrossTenants')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(2)
        ->and($violations[0])->toContain('app/Service.php: line 2: DB::table(\'users\')')
        ->and($violations[1])->toContain('app/Trait.php: QueriesAcrossTenants no longer removes a tenant scope', 'remove it');
});

it('bypasses the tenant scope in one trait and queries a modeled table raw nowhere', function (): void {
    $root = dirname(__DIR__, 2);
    $guard = new TenantScopeBypassGuard(new PhpFileInspector(), $root, 'app', 'app/Models', ClassSourcePath::of(QueriesAcrossTenants::class, $root), 'QueriesAcrossTenants');

    expect($guard->tables())->toContain('users', 'orders', 'category_product')
        ->and($guard->violations())->toBe([]);
});
