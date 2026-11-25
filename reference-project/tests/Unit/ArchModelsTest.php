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
use Illuminate\Support\Str;
use Tests\Support\ArchChecks;
use Tests\Support\Architecture\PhpCallReader;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\ClassNames;
use Tests\Support\Globs;
use Tests\Support\InstancesOf;
use Tests\Support\NestedValue;

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

test('a tenant model creates with its tenant, unless its tenant is optional', function (): void {
    $root = dirname(__DIR__, 2);
    $offenders = [];

    foreach (new PhpFileInspector()->phpFiles($root . '/app/Models/Tenant') as $file) {
        $class = new ReflectionClass(ClassNames::of('App\Models\Tenant\\' . str_replace('/', '\\', substr($file, strlen($root . '/app/Models/Tenant/'), -4))));
        $scope = $class->getAttributes(ScopedBy::class)[0] ?? null;

        if (! in_array(CreatesWithTenant::class, class_uses_recursive($class->getName()), true) && $scope?->getArguments() !== [NullableTenantScope::class]) {
            $offenders[] = $class->getName();
        }
    }

    expect($offenders)->toBe([]);
});

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

test('only the tenant row lock locks the row of a tenant', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $pattern = '/(?:\bTenant::|table\([\'"]tenants[\'"]\))[^;]*?->(?:lockForUpdate|lock|sharedLock)\(/s';
    $lock = (string) realpath((string) new ReflectionClass(TenantRowLock::class)->getFileName());
    $offenders = [];

    foreach ($inspector->phpFiles($root . '/app') as $file) {
        $path = substr($file, strlen($root) + 1);

        if (realpath($file) === $lock) {
            continue;
        }

        if (preg_match($pattern, (string) file_get_contents($file)) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([])
        ->and(preg_match($pattern, "Tenant::query()\n    ->whereKey(\$id)\n    ->lockForUpdate()\n    ->firstOrFail();"))->toBe(1)
        ->and(preg_match($pattern, "DB::table('tenants')->where('id', \$id)->lock('for no key update')->first();"))->toBe(1)
        ->and(preg_match($pattern, "\$tenant = Tenant::current();\n\$user = User::query()->lockForUpdate()->first();"))->toBe(0);
});

test('model date casts are immutable', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $offenders = [];

    foreach ($inspector->phpFiles($root . '/app/Models') as $file) {
        if (ArchChecks::castsDateMutably($inspector, $file)) {
            $offenders[] = substr($file, strlen($root) + 1);
        }
    }

    expect($offenders)->toBe([]);
});

test('models declare casts, fillable, accessors and boot hooks one way', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $patterns = [
        'casts property' => '/protected\s+\$casts\s*=/',
        'guarded property' => '/protected\s+\$guarded\s*=/',
        'get attribute accessor' => '/function\s+get[A-Z]\w*Attribute\s*\(/',
        'set attribute mutator' => '/function\s+set[A-Z]\w*Attribute\s*\(/',
        'attribute constructor' => '/new\s+Attribute\s*\(/',
        'boot method' => '/static\s+function\s+boot\s*\(/',
    ];
    $offenders = [];

    foreach ($inspector->phpFiles($root . '/app/Models') as $file) {
        $source = (string) file_get_contents($file);

        foreach ($patterns as $convention => $pattern) {
            if (preg_match($pattern, $source) === 1) {
                $offenders[] = substr($file, strlen($root) + 1) . ': ' . $convention;
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('the tenant scope is bypassed in one trait, and a modeled table is never queried raw', function (): void {
    $root = dirname(__DIR__, 2);
    $bypassMethods = ['withoutglobalscope', 'withoutglobalscopes', 'newquerywithoutscope', 'newquerywithoutscopes'];
    $tables = [];
    $models = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/Models', FilesystemIterator::SKIP_DOTS));

    foreach ($models as $file) {
        $file = InstancesOf::one($file, SplFileInfo::class);
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $reflection = new ReflectionClass(ClassNames::of('App\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($root . '/app/'), -4))));

        if ($reflection->isSubclassOf(Model::class) && ! $reflection->isAbstract()) {
            $tables[] = ArchChecks::declaredTable($file->getPathname()) ?? Str::snake(Str::pluralStudly($reflection->getShortName()));
        }
    }

    $findings = static function (string $source) use ($bypassMethods, $tables): array {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($source),
            static fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));
        $found = [];

        foreach ($tokens as $index => $token) {
            $next = $tokens[$index + 1] ?? null;

            if ($token->is(T_STRING) && in_array(strtolower($token->text), $bypassMethods, true) && $next?->text === '(') {
                $found[] = $token->text . ' line ' . $token->line;
            }

            if ($token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && in_array(ltrim($token->text, '\\'), ['DB', 'Illuminate\\Support\\Facades\\DB'], true)
                && $next?->is(T_DOUBLE_COLON) === true && ($tokens[$index + 2] ?? null)?->text === 'table' && ($tokens[$index + 3] ?? null)?->text === '('
                && ($tokens[$index + 4] ?? null)?->is(T_CONSTANT_ENCAPSED_STRING) === true) {
                $table = (string) preg_replace('/ as .*$/i', '', trim($tokens[$index + 4]->text, '\'"'));

                if (in_array($table, $tables, true)) {
                    $found[] = "DB::table('" . $table . "') line " . $token->line;
                }
            }
        }

        return $found;
    };

    // Every read of another tenant's rows is `->acrossTenants()` on the model, a scope of QueriesAcrossTenants: the one file that removes the tenant scopes.
    $trait = (string) realpath((string) new ReflectionClass(QueriesAcrossTenants::class)->getFileName());
    $offenders = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        $file = InstancesOf::one($file, SplFileInfo::class);
        if ($file->getExtension() !== 'php' || realpath($file->getPathname()) === $trait) {
            continue;
        }

        foreach ($findings((string) file_get_contents($file->getPathname())) as $line) {
            $offenders[] = 'App\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($root . '/app/'), -4)) . ': ' . $line;
        }
    }

    expect($offenders)->toBe([])
        ->and($findings((string) file_get_contents($trait)))->not->toBe([])
        ->and($tables)->toContain('users', 'orders', 'category_product')
        ->and($findings('<?php $q->withoutGlobalScope(TenantScope::class)->acrossTenants(); User::withoutGlobalScopes();'))->toHaveCount(2)
        ->and($findings('<?php $q = DB::table("order_lines"); $r = \\Illuminate\\Support\\Facades\\DB::table(\'orders as o\');'))->toHaveCount(2)
        ->and($findings('<?php $q = DB::table("migrations"); $r = DB::table($table); $s = User::query()->acrossTenants();'))->toBe([]);
});

test('queries are written as scopes of the model: no custom Eloquent builder and no query-string builder', function (): void {
    $root = dirname(__DIR__, 2);
    $pattern = '/\bnewEloquentBuilder\b|UseEloquentBuilder|Spatie\\\\QueryBuilder/';
    $offenders = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        $file = InstancesOf::one($file, SplFileInfo::class);
        if ($file->getExtension() === 'php' && preg_match($pattern, (string) file_get_contents($file->getPathname())) === 1) {
            $offenders[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }

    expect($offenders)->toBe([])
        ->and(preg_match($pattern, 'public function newEloquentBuilder($query)'))->toBe(1)
        ->and(preg_match($pattern, 'use Spatie\QueryBuilder\QueryBuilder;'))->toBe(1)
        ->and(preg_match($pattern, '#[UseEloquentBuilder(XBuilder::class)]'))->toBe(1);
});

test('queries start at Model::query(): a model never gets a magic static call', function (): void {
    $root = dirname(__DIR__, 2);
    $magic = static function (string $path) use ($root): array {
        $offenders = [];

        foreach (PhpCallReader::staticCalls($path) as $call) {
            if (class_exists($call['class']) && is_subclass_of($call['class'], Model::class) && ! method_exists($call['class'], $call['method'])) {
                $offenders[] = ltrim(substr($path, strlen($root)), '/') . ' line ' . $call['line'];
            }
        }

        return $offenders;
    };
    $offenders = [];

    foreach (['app', 'bootstrap', 'config', 'database'] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            $file = InstancesOf::one($file, SplFileInfo::class);
            if ($file->getExtension() === 'php') {
                $offenders = [...$offenders, ...$magic($file->getPathname())];
            }
        }
    }

    $fixture = tempnam(sys_get_temp_dir(), 'query');
    file_put_contents($fixture, '<?php namespace App\Models\Tenant; use App\Models\Tenant\User as Person; final class Probe extends Order { public function a() { return [Person::where("a", 1), User::find(1), parent::where("a", 1), self::query()->where("a", 1), User::query()->where("a", 1), User::withTrashed(), User::factory(), parent::boot()]; } }');
    $fixtureOffenders = $magic($fixture);
    unlink($fixture);

    expect($offenders)->toBe([])
        ->and($fixtureOffenders)->toHaveCount(4);
});
