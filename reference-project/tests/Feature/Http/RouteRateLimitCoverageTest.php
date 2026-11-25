<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\GuardsAgainstDatabaseAccess;
use Tests\Support\Feature\Http\RouteRateLimitCoverageHelpers;

uses(GuardsAgainstDatabaseAccess::class);

const RATE_LIMIT_DEDICATED_LIMITERS = [
    'newsletter' => ['reason' => 'the sign-up form of the newsletter keeps its own limit per address', 'routes' => ['newsletter.subscribe']],
];

const RATE_LIMIT_OUT_OF_SCOPE_SOURCES = ['tests'];

it('gives every route a category or a dedicated limiter, by the default reads and writes limiters of the web groups or its own', function (): void {
    $problems = collect(Route::getRoutes()->getRoutes())
        ->reject(static fn (RoutingRoute $route): bool => RouteRateLimitCoverageHelpers::rateLimitOutOfScope($route))
        ->mapWithKeys(static fn (RoutingRoute $route): array => [implode('|', $route->methods()) . ' ' . RouteRateLimitCoverageHelpers::rateLimitRouteIdentifier($route) => RouteRateLimitCoverageHelpers::rateLimitRouteProblem($route)])
        ->filter()
        ->map(static fn (string $problem, string $route): string => $route . ' ' . $problem)
        ->values()
        ->all();

    expect($problems)->toBe([]);
});

it('starts every upload from the uploads bucket', function (string $name, string $category): void {
    $route = Route::getRoutes()->getByName($name);

    expect($route)->not->toBeNull()
        ->and(RouteRateLimitCoverageHelpers::rateLimitRouteCategories(RouteRateLimitCoverageHelpers::rateLimitNamedRoute($name)))->toBe([$category]);
})->with([
    ['admin.products.images.store', 'uploads'],
]);

it('keeps a destructive action out of the checkout bucket', function (string $name): void {
    $limiters = RouteRateLimitCoverageHelpers::rateLimitRouteLimiters(RouteRateLimitCoverageHelpers::rateLimitNamedRoute($name));

    expect($limiters)->toContain('writes')
        ->and(RouteRateLimitCoverageHelpers::rateLimitRouteCategories(RouteRateLimitCoverageHelpers::rateLimitNamedRoute($name)))->toBe([]);
})->with(['orders.cancel']);

it('reports a route that slips through without a category', function (): void {
    $unlimited = new RoutingRoute(['POST'], '_unlimited', static fn (): string => 'ok')->middleware(['auth']);
    $plain = new RoutingRoute(['POST'], '_plain', static fn (): string => 'ok')->middleware(['auth', 'throttle:10,1']);
    $borrowed = new RoutingRoute(['POST'], '_borrowed', static fn (): string => 'ok')->middleware(['auth', 'throttle:newsletter'])->name('_borrowed');
    $page = new RoutingRoute(['GET', 'HEAD'], '_page', static fn (): string => 'ok')->middleware(['auth', 'throttle:writes']);
    $write = new RoutingRoute(['DELETE'], '_write', static fn (): string => 'ok')->middleware(['auth', 'throttle:writes']);
    $read = new RoutingRoute(['GET', 'HEAD'], '_read', static fn (): string => 'ok')->middleware(['auth', 'throttle:reads']);
    $mixed = new RoutingRoute(['GET', 'POST', 'HEAD'], '_mixed', static fn (): string => 'ok')->middleware(['auth', 'throttle:reads']);
    $grouped = new RoutingRoute(['GET', 'HEAD'], '_grouped', static fn (): string => 'ok')->middleware(['web']);
    $twice = new RoutingRoute(['POST'], '_twice', static fn (): string => 'ok')->middleware(['throttle:checkout', 'throttle:uploads']);

    expect(RouteRateLimitCoverageHelpers::rateLimitRouteProblem($unlimited))->toBe('has no rate limit category')
        ->and(RouteRateLimitCoverageHelpers::rateLimitRouteProblem($plain))->toBe('uses a limiter that is not a category: 10,1')
        ->and(RouteRateLimitCoverageHelpers::rateLimitRouteProblem($borrowed))->toBe('uses the dedicated limiter newsletter outside the routes it is meant for')
        ->and(RouteRateLimitCoverageHelpers::rateLimitRouteProblem($page))->toBe('has no rate limit category')
        ->and(RouteRateLimitCoverageHelpers::rateLimitRouteProblem($write))->toBeNull()
        ->and(RouteRateLimitCoverageHelpers::rateLimitRouteProblem($read))->toBeNull()
        ->and(RouteRateLimitCoverageHelpers::rateLimitRouteProblem($mixed))->toBe('has no rate limit category')
        ->and(RouteRateLimitCoverageHelpers::rateLimitRouteProblem($grouped))->toBeNull()
        ->and(RouteRateLimitCoverageHelpers::rateLimitRouteProblem($twice))->toBe('counts in more than one bucket: checkout, uploads');
});

it('puts every route of the web groups in the reads and writes tiers', function (string $group): void {
    $limiters = RouteRateLimitCoverageHelpers::rateLimitRouteLimiters(new RoutingRoute(['GET'], '_group', static fn (): string => 'ok')->middleware([$group]));

    expect($limiters)->toContain('reads', 'writes');
})->with(['web', 'web_landlord']);
