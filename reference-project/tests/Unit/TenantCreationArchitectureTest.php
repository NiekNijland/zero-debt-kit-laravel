<?php

declare(strict_types=1);

use App\Models\Landlord\Tenant;
use App\Modules\Tenants\Support\CreatesWithTenant;
use App\Modules\Tenants\Support\NullableTenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TenantCreationGuard;

uses()->group('arch');

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
