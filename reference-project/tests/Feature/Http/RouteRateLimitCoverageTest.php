<?php

declare(strict_types=1);

use App\Http\Controllers\Order\IndexController;
use App\Http\Controllers\Order\StoreController;
use Illuminate\Routing\Route as RoutingRoute;
use Tests\Concerns\GuardsAgainstDatabaseAccess;
use Tests\Support\ApplicationRoutes;
use Tests\Support\Feature\Http\RouteRateLimitCoverageHelpers;

uses(GuardsAgainstDatabaseAccess::class);

it('puts every route of the application behind one registered limiter, a write limiter for a write', function (): void {
    expect(RouteRateLimitCoverageHelpers::problems(['app', 'routes'], ['writes']))->toBe([]);
});

it('reports a route that slips through without a named limiter', function (): void {
    $read = static fn (string ...$middleware): RoutingRoute => ApplicationRoutes::forController(['GET', 'HEAD'], 'orders', IndexController::class)->middleware($middleware);
    $write = static fn (string ...$middleware): RoutingRoute => ApplicationRoutes::forController(['POST'], 'orders', StoreController::class)->middleware($middleware);

    expect(RouteRateLimitCoverageHelpers::problem($read(), ['writes']))->toBe('has no throttle:<name> limiter')
        ->and(RouteRateLimitCoverageHelpers::problem($read('throttle:60,1'), ['writes']))->toBe('uses 60,1, which is not a limiter registered in the rate-limit provider')
        ->and(RouteRateLimitCoverageHelpers::problem($read('throttle'), ['writes']))->toBe('uses a throttle without a limiter name, which is not a limiter registered in the rate-limit provider')
        ->and(RouteRateLimitCoverageHelpers::problem($read('throttle:reads', 'throttle:writes'), ['writes']))->toBe('counts in more than one limiter: reads, writes')
        ->and(RouteRateLimitCoverageHelpers::problem($read('throttle:writes'), ['writes']))->toBe('reads with the write limiter writes')
        ->and(RouteRateLimitCoverageHelpers::problem($write('throttle:reads'), ['writes']))->toBe('writes with the read limiter reads')
        ->and(RouteRateLimitCoverageHelpers::problem($read('throttle:reads'), ['writes']))->toBeNull()
        ->and(RouteRateLimitCoverageHelpers::problem($write('throttle:writes'), ['writes']))->toBeNull();
});
