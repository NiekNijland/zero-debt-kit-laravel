<?php

declare(strict_types=1);

use App\Models\Landlord\Tenant;
use App\Modules\Tenants\Support\CreatesWithTenant;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\Globs;
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
