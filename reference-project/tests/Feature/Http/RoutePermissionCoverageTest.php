<?php

declare(strict_types=1);

use App\Modules\Roles\Enums\Permission;
use App\Modules\Roles\Enums\PermissionArea;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\GuardsAgainstDatabaseAccess;
use Tests\Support\Feature\Http\RoutePermissionCoverageHelpers;
use Tests\Support\Narrow;

uses(GuardsAgainstDatabaseAccess::class);

it('checks a permission or a policy on the route itself for every route behind Authenticate', function (): void {
    expect(RoutePermissionCoverageHelpers::permissionUncoveredRoutes())->toBe([]);
});

it('counts route middleware, and a policy ability only when it names the model in the URL', function (): void {
    $route = static fn (string $uri, Closure $action, array $middleware): RoutingRoute => new RoutingRoute(['POST'], $uri, $action)->middleware(Narrow::strings($middleware));

    expect(RoutePermissionCoverageHelpers::permissionRouteIsInScope($route('_guest', static fn (): string => 'ok', ['web', 'guest'])))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::permissionRouteIsInScope($route('_signed', static fn (): string => 'ok', ['web', 'signed'])))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::permissionRouteIsInScope($route('_public', static fn (): string => 'ok', ['web'])))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::permissionRouteIsInScope($route('_auth', static fn (): string => 'ok', ['web', 'auth'])))->toBeTrue()
        ->and(RoutePermissionCoverageHelpers::permissionRouteIsAuthorized($route('_bare', static fn (): string => 'ok', ['web', 'auth'])))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::permissionRouteIsAuthorized($route('_area', static fn (): string => 'ok', ['web', 'auth', 'can:' . Permission::BACKOFFICE_OPEN->value])))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::permissionRouteIsAuthorized($route('_permission', static fn (): string => 'ok', ['web', 'auth', 'can:' . Permission::BACKOFFICE_OVERVIEW->value])))->toBeTrue()
        ->and(RoutePermissionCoverageHelpers::permissionRouteIsAuthorized($route('_spatie', static fn (): string => 'ok', ['web', 'auth', 'permission:a|b'])))->toBeTrue()
        ->and(RoutePermissionCoverageHelpers::permissionRouteIsAuthorized($route('_withoutMiddleware', static fn (): string => 'ok', ['web', 'auth', 'can:' . Permission::BACKOFFICE_OVERVIEW->value])->withoutMiddleware('can:' . Permission::BACKOFFICE_OVERVIEW->value)))->toBeFalse();
});

it('has a case of the account area for every self-service permission and gives it to every signed-in user through the gate', function (): void {
    foreach (Permission::cases() as $permission) {
        if ($permission->area() !== PermissionArea::ACCOUNT) {
            continue;
        }

        expect(Gate::has($permission->value))->toBeTrue()
            ->and($permission->defaultRoles())->toBe([]);
    }
});
