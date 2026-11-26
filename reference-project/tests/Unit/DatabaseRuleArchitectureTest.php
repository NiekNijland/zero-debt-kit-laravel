<?php

declare(strict_types=1);

use App\Modules\Tenants\Support\NullableTenantScope;
use App\Modules\Tenants\Support\TenantScope;
use Tests\Support\Architecture\DatabaseRuleGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

// The tenant facts of this project; the detector holds none of them. A single-tenant project passes no tenant and keeps the model-class half of the rule.
$tenant = ['scope' => TenantScope::class, 'nullableScope' => NullableTenantScope::class, 'column' => 'tenant_id'];

dataset('database rules that skip the model or the tenant', [
    'a table name' => ["<?php\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::exists('users', 'id')->where('tenant_id', \$id);\n", 'without a model class'],
    'a table name by the fully qualified facade' => ["<?php\n\$r = \\Illuminate\\Validation\\Rule::unique('coupons', 'name')->whereNull('tenant_id');\n", 'without a model class'],
    'the table of a new model' => ["<?php\nuse App\\Models\\Tenant\\Product;\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::exists(new Product()->getTable(), 'sku')->where('tenant_id', \$id);\n", 'without a model class'],
    'a class that is not a model' => ["<?php\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::exists(Rule::class, 'id');\n", 'without a model class'],
    'a rule object built by hand' => ["<?php\nuse Illuminate\\Validation\\Rules\\Exists;\n\$r = new Exists('users', 'id');\n", 'new Exists()'],
    'a string rule' => ["<?php\n\$rules = ['locale' => ['required', 'exists:locales,code']];\n", "the string rule 'exists:'"],
    'a string rule in double quotes after a pipe' => ["<?php\n\$rules = ['name' => \"required|unique:tenants,name\"];\n", "the string rule 'unique:'"],
    'a string rule with an interpolated table' => ["<?php\n\$rules = ['id' => [\"exists:{\$table},id\"]];\n", "the string rule 'exists:'"],
    'a scoped model without tenant_id' => ["<?php\nuse App\\Models\\Tenant\\User;\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::exists(User::class, 'id');\n", 'Rule::exists(User::class) without tenant_id'],
    'a scoped model with tenant_id only as its column' => ["<?php\nuse App\\Models\\Tenant\\User;\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::unique(User::class, 'tenant_id')->where('type', 'email');\n", 'Rule::unique(User::class) without tenant_id'],
    'a scoped model with tenant_id on another rule' => ["<?php\nuse App\\Models\\Tenant\\Customer;\nuse Illuminate\\Validation\\Rule;\n\$r = [Rule::unique(Customer::class, 'name')->ignore(\$id), \$other->where('tenant_id', \$id)];\n", 'Rule::unique(Customer::class) without tenant_id'],
    'a scoped model that excludes a tenant' => ["<?php\nuse App\\Models\\Tenant\\Coupon;\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::unique(Coupon::class, 'name')->whereNot('tenant_id', \$id);\n", 'Rule::unique(Coupon::class) without tenant_id'],
    'the rows of no tenant of a model that always has one' => ["<?php\nuse App\\Models\\Tenant\\User;\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::unique(User::class, 'email')->whereNull('tenant_id');\n", 'Rule::unique(User::class) without tenant_id'],
]);

it('detects an existence or uniqueness rule that skips the model or the tenant', function (string $source, string $reason) use ($tenant): void {
    $violations = (new DatabaseRuleGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', $tenant))->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason)
        ->and($violations[0])->toContain('::class');
})->with('database rules that skip the model or the tenant');

it('accepts a rule on the model class that names tenant_id when the model belongs to a tenant', function () use ($tenant): void {
    $source = <<<'PHP'
        <?php
        use App\Models\Landlord\Locale;
        use App\Models\Landlord\Tenant;
        use App\Models\Tenant\Coupon;
        use App\Models\Tenant\User;
        use Illuminate\Database\Query\Builder;
        use Illuminate\Support\Facades\Storage;
        use Illuminate\Validation\Rule;
        $rules = [
            Rule::exists(User::class, 'id')->where('tenant_id', $this->tenant()->id),
            Rule::unique(User::class, 'email')->where('type', 'email')->where('tenant_id', $tenant->id)->ignore($id),
            Rule::unique(Coupon::class, 'name')->where(fn (Builder $query): Builder => $query->where('tenant_id', $tenantId))->whereNull('deleted_at'),
            Rule::unique(Coupon::class, 'name')->whereNull('tenant_id')->ignore($id),
            Rule::unique(Tenant::class, 'name')->ignore($tenantId),
            Rule::exists(Locale::class, 'code'),
            Rule::in(['unique', 'exists']),
            'in:exists,unique',
            'required_unless:exists,1',
        ];
        $exists = Storage::exists('unique') && $query->exists() && $collection->unique('tenant_id')->isNotEmpty();
        PHP;

    expect((new DatabaseRuleGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', $tenant))->inSource($source))->toBe([]);
});

it('keeps the model-class half of the rule in a project with no tenant', function (): void {
    $guard = new DatabaseRuleGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app');
    $tableName = "<?php\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::exists('users', 'id');\n";
    $model = "<?php\nuse Illuminate\\Foundation\\Auth\\User;\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::exists(User::class, 'id');\n";

    expect($guard->inSource($tableName))->toHaveCount(1)
        ->and($guard->inSource($tableName)[0])->toContain('without a model class')
        ->and($guard->inSource($tableName)[0])->not->toContain('tenant')
        ->and($guard->inSource($model))->toBe([]);
});

dataset('rules on a scoped model', [
    'no tenant column in the chain' => ["Rule::exists(ZdkScopedNote::class, 'id')", 'Rule::exists(ZdkScopedNote::class) without tenant_id in its chain'],
    'the tenant column only as the checked column' => ["Rule::unique(ZdkScopedNote::class, 'tenant_id')->where('type', 'x')", 'Rule::unique(ZdkScopedNote::class) without tenant_id in its chain'],
    'the rows of no tenant of a model that always has one' => ["Rule::exists(ZdkScopedNote::class, 'id')->whereNull('tenant_id')", 'Rule::exists(ZdkScopedNote::class) without tenant_id in its chain'],
]);

it('fails a rule on a model with the tenant scope that skips the tenant (the model is declared in a sandbox file, so the example runs without the application)', function (string $rule, string $words): void {
    $root = SourceSandbox::create(['app/ZdkScopedNote.php' => "<?php\nnamespace Zdk;\nuse Illuminate\\Database\\Eloquent\\Attributes\\ScopedBy;\nuse Illuminate\\Database\\Eloquent\\Model;\n#[ScopedBy(ZdkTenantScope::class)]\nfinal class ZdkScopedNote extends Model {}\n"]);

    try {
        class_exists('Zdk\\ZdkScopedNote', false) || require $root . '/app/ZdkScopedNote.php';
        $violations = (new DatabaseRuleGuard(new PhpFileInspector(), $root, 'app', ['scope' => 'Zdk\\ZdkTenantScope', 'nullableScope' => 'Zdk\\ZdkNullableTenantScope', 'column' => 'tenant_id']))
            ->inSource("<?php\nnamespace Zdk;\nuse Illuminate\\Validation\\Rule;\n\$r = " . $rule . ";\n");
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($words);
})->with('rules on a scoped model');

it('accepts a rule on a model with the tenant scope that names the tenant, and drops the tenant half without a tenant argument', function (): void {
    $root = SourceSandbox::create(['app/ZdkScopedNote.php' => "<?php\nnamespace Zdk;\nuse Illuminate\\Database\\Eloquent\\Attributes\\ScopedBy;\nuse Illuminate\\Database\\Eloquent\\Model;\n#[ScopedBy(ZdkTenantScope::class)]\nfinal class ZdkScopedNote extends Model {}\n"]);

    try {
        class_exists('Zdk\\ZdkScopedNote', false) || require $root . '/app/ZdkScopedNote.php';
        $facts = ['scope' => 'Zdk\\ZdkTenantScope', 'nullableScope' => 'Zdk\\ZdkNullableTenantScope', 'column' => 'tenant_id'];
        $named = "<?php\nnamespace Zdk;\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::exists(ZdkScopedNote::class, 'id')->where('tenant_id', \$id);\n";
        $skipped = "<?php\nnamespace Zdk;\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::exists(ZdkScopedNote::class, 'id');\n";
        $withTenant = new DatabaseRuleGuard(new PhpFileInspector(), $root, 'app', $facts);
        $withoutTenant = new DatabaseRuleGuard(new PhpFileInspector(), $root, 'app');
    } finally {
        SourceSandbox::remove($root);
    }

    expect($withTenant->inSource($named))->toBe([])
        ->and($withTenant->inSource($skipped))->toHaveCount(1)
        ->and($withoutTenant->inSource($skipped))->toBe([]);
});

it('reads a tree under a dot directory and names the file relative to the root', function () use ($tenant): void {
    $root = SourceSandbox::create([
        'app/Bad.php' => "<?php\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::exists('users', 'id');\n",
        'app/Good.php' => "<?php\n",
    ], prefix: '.checkout-');

    try {
        $violations = (new DatabaseRuleGuard(new PhpFileInspector(), $root, 'app', $tenant))->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Bad.php:3: Rule::exists() without a model class');
});

it('writes every existence and uniqueness rule in app on its model class and inside its tenant', function () use ($tenant): void {
    expect((new DatabaseRuleGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', $tenant))->violations())->toBe([]);
});
