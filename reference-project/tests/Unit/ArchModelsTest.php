<?php

declare(strict_types=1);

use App\Models\Landlord\Tenant;
use App\Modules\Tenants\Support\CreatesWithTenant;
use App\Modules\Tenants\Support\NullableTenantScope;
use App\Modules\Tenants\Support\QueriesAcrossTenants;
use App\Modules\Tenants\Support\TenantRowLock;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\Architecture\ClassSourcePath;
use Tests\Support\Architecture\DateCastGuard;
use Tests\Support\Architecture\EloquentBuilderGuard;
use Tests\Support\Architecture\ModelConventionGuard;
use Tests\Support\Architecture\ModelMagicStaticCallGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TenantCreationGuard;
use Tests\Support\Architecture\TenantRowLockGuard;
use Tests\Support\Architecture\TenantScopeBypassGuard;
use Tests\Support\Globs;
use Tests\Support\NestedValue;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$appPath = dirname(__DIR__, 2) . '/app';
$modelDirectories = NestedValue::strings(collect([
    'App\Models',
    ...collect(Globs::of($appPath . '/Modules/*/Models'))
        ->map(function (string $path): string {
            $moduleName = basename(dirname($path));

            return "App\\Modules\\{$moduleName}\\Models";
        }),
])->toArray());

arch('Landlord models are not scoped to a tenant')
    ->expect('App\Models\Landlord')
    ->not->toUseTrait(CreatesWithTenant::class)
    ->and('App\Models\Landlord')
    ->not->toHaveAttribute(ScopedBy::class);

arch('Tenant models have a tenant relation')
    ->expect('App\Models\Tenant')
    ->toHaveMethod('tenant')
    ->toHaveAttribute(ScopedBy::class);

arch('Tenant model extends spatie tenant')
    ->expect(Tenant::class)
    ->toExtend(Spatie\Multitenancy\Models\Tenant::class);

arch('factories')
    ->expect('Database\Factories\Tenant')
    ->toExtend(Factory::class)
    ->toHaveMethod('definition')
    ->toOnlyBeUsedIn($modelDirectories);

arch('factories set the tenant with for() instead of a tenant state')
    ->expect('Database\Factories')
    ->not->toHaveMethod('tenant');

arch('models')
    ->expect('App\Models')
    ->toExtend(Model::class)
    ->toOnlyBeUsedIn([
        'App\Policies',
        CreatesWithTenant::class,
        'App\Support',
        'App\Http',
        'App\Models',
        'App\Modules',
        'App\Providers',
        'Database\Factories',
        'Database\Seeders',
        'App\View\Composers',
    ]);

dataset('tenant models by their traits and their scope', [
    'no trait and the plain scope' => [[], [], true],
    'no trait and no scope at all' => [[], null, true],
    'the trait' => [[CreatesWithTenant::class], [], false],
    'the trait among others' => [['Other\Trait', CreatesWithTenant::class], [], false],
    'an optional tenant' => [[], [NullableTenantScope::class], false],
    'another scope than the optional one' => [[], ['App\OtherScope'], true],
]);

it('detects a tenant model that creates without its tenant', function (array $traits, ?array $scope, bool $missing): void {
    $guard = new TenantCreationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models/Tenant', 'App\Models\Tenant\\', CreatesWithTenant::class, ScopedBy::class, NullableTenantScope::class);

    expect($guard->isMissingTenant($traits, $scope))->toBe($missing);
})->with('tenant models by their traits and their scope');

it('creates every tenant model with its tenant, unless its tenant is optional', function (): void {
    $guard = new TenantCreationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models/Tenant', 'App\Models\Tenant\\', CreatesWithTenant::class, ScopedBy::class, NullableTenantScope::class);

    expect($guard->violations())->toBe([]);
});

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

dataset('date casts', [
    'datetime cast' => ['<?php return [\'at\' => \'datetime\'];', true],
    'date cast with a format' => ['<?php return [\'at\' => \'date:Y-m-d\'];', true],
    'immutable cast' => ['<?php return [\'at\' => \'immutable_datetime\'];', false],
    'immutable date with a format' => ['<?php return [\'at\' => \'immutable_date:Y-m-d\'];', false],
    'a rule list that names date' => ['<?php return [\'required\', \'date\'];', false],
    'a rule string' => ['<?php return [\'at\' => \'date|required\'];', false],
]);

it('detects a mutable date cast and accepts the immutable ones and a validation rule', function (string $source, bool $mutable): void {
    $violations = new DateCastGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models')->inSource($source);

    expect($violations !== [])->toBe($mutable)
        ->and($mutable ? $violations[0] : '')->toContain($mutable ? 'cast it as immutable_datetime or immutable_date' : '');
})->with('date casts');

it('reads the model folder below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/Models/A.php' => "<?php\nreturn ['at' => 'datetime'];\n", 'app/Models/B.php' => "<?php\nreturn ['at' => 'immutable_datetime'];\n"], prefix: '.checkout-');

    try {
        $violations = new DateCastGuard(new PhpFileInspector(), $root, 'app/Models')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Models/A.php: a date is cast as datetime or date');
});

it('casts every model date as immutable', function (): void {
    expect(new DateCastGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models')->violations())->toBe([]);
});

dataset('model conventions that fail', [
    'a casts property' => ["<?php\nclass A { protected \$casts = []; }\n", 'a casts property: declare the casts in the casts() method'],
    'a guarded property' => ["<?php\nclass A { protected \$guarded = []; }\n", 'list what is fillable'],
    'a get accessor' => ["<?php\nclass A { public function getNameAttribute() {} }\n", 'a method that returns an Attribute'],
    'a set mutator' => ["<?php\nclass A { public function setNameAttribute(\$v) {} }\n", 'a method that returns an Attribute'],
    'an Attribute constructor' => ["<?php\nclass A { public function name() { return new Attribute(get: fn () => 1); } }\n", 'Attribute::make'],
    'a boot method' => ["<?php\nclass A { protected static function boot() { parent::boot(); } }\n", 'booted()'],
]);

it('detects a model that declares casts, fillable, accessors or boot hooks the old way', function (string $source, string $words): void {
    $violations = new ModelConventionGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($words);
})->with('model conventions that fail');

it('accepts the casts method, a fillable list, the booted hook and a method that only starts like a boot method', function (): void {
    $source = "<?php\nclass A { protected \$fillable = []; protected function casts(): array { return []; } protected static function booted(): void {} public function getName() {} public static function bootstrap() {} }\n";

    expect(new ModelConventionGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models')->inSource($source))->toBe([]);
});

it('reads the model folder below a checkout under a dot directory for the conventions and the builders', function (): void {
    $root = SourceSandbox::create(['app/Models/A.php' => "<?php\nclass A { protected \$casts = []; public function newEloquentBuilder(\$q) {} }\n"], prefix: '.checkout-');

    try {
        $conventions = new ModelConventionGuard(new PhpFileInspector(), $root, 'app/Models')->violations();
        $builders = new EloquentBuilderGuard(new PhpFileInspector(), $root, 'app', 'Spatie\QueryBuilder')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($conventions)->toBe(['app/Models/A.php: a casts property: declare the casts in the casts() method'])
        ->and($builders)->toHaveCount(1)
        ->and($builders[0])->toStartWith('app/Models/A.php: a custom Eloquent builder');
});

it('declares casts, fillable, accessors and boot hooks one way in every model', function (): void {
    expect(new ModelConventionGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models')->violations())->toBe([]);
});

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

dataset('builders and query-string packages', [
    'a custom builder method' => ['public function newEloquentBuilder($query)'],
    'the query-string package' => ['use Spatie\QueryBuilder\QueryBuilder;'],
    'the builder attribute' => ['#[UseEloquentBuilder(XBuilder::class)]'],
]);

it('detects a custom Eloquent builder and the query-string package', function (string $source): void {
    $violations = new EloquentBuilderGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'Spatie\QueryBuilder')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('write the query as a scope of the model');
})->with('builders and query-string packages');

it('accepts a scope and a package with a similar name', function (): void {
    $guard = new EloquentBuilderGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'Spatie\QueryBuilder');

    expect($guard->inSource('public function scopeActive($query) {} use Spatie\QueryBuilderHelper\X;'))->toBe([]);
});

it('writes queries as scopes of the model, with no custom builder and no query-string builder', function (): void {
    expect(new EloquentBuilderGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'Spatie\QueryBuilder')->violations())->toBe([]);
});

it('detects a magic static call on a model and accepts the near misses (examples that name application models: reasoned, not run without the application)', function (): void {
    $guard = new ModelMagicStaticCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app']);
    $source = '<?php namespace App\Models\Tenant; use App\Models\Tenant\User as Person; final class Probe extends Order { public function a() { return [Person::where("a", 1), User::find(1), parent::where("a", 1), self::query()->where("a", 1), User::query()->where("a", 1), User::withTrashed(), User::factory(), parent::boot()]; } }';
    $violations = $guard->inSource($source);

    expect($violations)->toHaveCount(4)
        ->and($violations[0])->toContain('where() is a magic static call on a model: start the query at User::query()');
});

it('starts every query at Model::query()', function (): void {
    expect(new ModelMagicStaticCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'config', 'database'])->violations())->toBe([]);
});
