<?php

declare(strict_types=1);

use App\Http\Controllers\Order\IndexController;
use App\Http\Controllers\Order\ShowController;
use App\Modules\Roles\Enums\Permission;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Routing\Route as RoutingRoute;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Tests\Concerns\GuardsAgainstDatabaseAccess;
use Tests\Support\ApplicationRoutes;
use Tests\Support\Feature\Http\RoutePermissionCoverageHelpers;

uses(GuardsAgainstDatabaseAccess::class);

// The facts of this project; the helper holds none of them.
$authenticating = [Authenticate::class];
$checking = [Authorize::class, PermissionMiddleware::class];
$areaGates = [Authorize::class . ':' . Permission::BACKOFFICE_OPEN->value];

it('puts a permission or a policy ability on the route of every route outside the public area', function () use ($authenticating, $checking, $areaGates): void {
    expect(RoutePermissionCoverageHelpers::uncoveredRoutes(['app', 'routes'], 'App\Http\Controllers', 'Web', $authenticating, $checking, $areaGates))->toBe([]);
});

it('reads the middleware of a route, and a policy ability only when it names the model in the URL', function () use ($authenticating, $checking, $areaGates): void {
    $index = static fn (string ...$middleware): RoutingRoute => ApplicationRoutes::forController(['GET'], 'orders', IndexController::class)->middleware($middleware);
    $show = static fn (string ...$middleware): RoutingRoute => ApplicationRoutes::forController(['GET'], 'orders/{order}', ShowController::class)->middleware($middleware);
    $permission = Authorize::class . ':' . Permission::BACKOFFICE_OVERVIEW->value;

    expect(RoutePermissionCoverageHelpers::isInScope($index('web'), $authenticating))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::isInScope($index('web', Authenticate::class), $authenticating))->toBeTrue()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($index(Authenticate::class), $checking, $areaGates))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($index(Authenticate::class, $areaGates[0]), $checking, $areaGates))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($index(Authenticate::class, $permission), $checking, $areaGates))->toBeTrue()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($index(Authenticate::class, PermissionMiddleware::class . ':a|b'), $checking, $areaGates))->toBeTrue()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($show(Authenticate::class, $permission), $checking, $areaGates))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($show(Authenticate::class, Authorize::class . ':view,order'), $checking, $areaGates))->toBeTrue()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($show(Authenticate::class, Authorize::class . ':view,other'), $checking, $areaGates))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($show(Authenticate::class, Authorize::class . ':view,order')->withoutMiddleware(Authorize::class . ':view,order'), $checking, $areaGates))->toBeFalse();
});
