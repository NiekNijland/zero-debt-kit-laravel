<?php

declare(strict_types=1);

use App\Modules\Tenants\Support\CreatesWithTenant;
use App\Modules\Tenants\Support\NullableTenantScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TenantCreationGuard;

uses()->group('arch');

$guard = fn (string $root): TenantCreationGuard => new TenantCreationGuard(new PhpFileInspector(), $root, 'app/Models/Tenant', 'App\Models\Tenant\\', CreatesWithTenant::class, ScopedBy::class, NullableTenantScope::class);

dataset('tenant models that create without their tenant', [
    'no trait and the plain scope' => [[], []],
    'no trait and no scope at all' => [[], null],
    'another scope than the optional one' => [[], ['App\OtherScope']],
]);

it('detects a tenant model that creates without its tenant', function (array $traits, ?array $scope) use ($guard): void {
    $messages = $guard(dirname(__DIR__, 2))->inClass('App\Models\Tenant\Order', $traits, $scope);

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain('App\Models\Tenant\Order is a tenant model that creates without its tenant: use CreatesWithTenant');
})->with('tenant models that create without their tenant');

dataset('tenant models that create with their tenant', [
    'the trait' => [[CreatesWithTenant::class], []],
    'the trait among others' => [['Other\Trait', CreatesWithTenant::class], []],
    'an optional tenant' => [[], [NullableTenantScope::class]],
]);

it('accepts a tenant model that has the trait or whose tenant is optional', function (array $traits, ?array $scope) use ($guard): void {
    expect($guard(dirname(__DIR__, 2))->inClass('App\Models\Tenant\Order', $traits, $scope))->toBe([]);
})->with('tenant models that create with their tenant');

it('creates every tenant model with its tenant, unless its tenant is optional', function () use ($guard): void {
    expect($guard(dirname(__DIR__, 2))->violations())->toBe([]);
});
