<?php

declare(strict_types=1);

use Tests\Support\Architecture\DatabaseRuleGuard;
use Tests\Support\Architecture\PhpFileInspector;

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

it('detects an existence or uniqueness rule that skips the model or the tenant', function (string $source, string $reason): void {
    $violations = (new DatabaseRuleGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app'))->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason)
        ->and($violations[0])->toContain('::class');
})->with('database rules that skip the model or the tenant');

it('accepts a rule on the model class that names tenant_id when the model belongs to a tenant', function (): void {
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

    expect((new DatabaseRuleGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app'))->inSource($source))->toBe([]);
});

it('writes every existence and uniqueness rule in app on its model class and inside its tenant', function (): void {
    expect((new DatabaseRuleGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app'))->violations())->toBe([]);
});
