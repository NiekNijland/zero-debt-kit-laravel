<?php

declare(strict_types=1);

use App\Modules\Roles\Enums\Permission;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Concerns\GuardsAgainstDatabaseAccess;
use Tests\Support\Feature\Routing\RouteFilesHelpers;
use Tests\Support\Narrow;

uses(GuardsAgainstDatabaseAccess::class);

it('keeps the routes that moved into routes/ exactly as they were', function (string $name, string $uri, array $methods, array $middleware, bool $onLandlordDomain): void {
    $route = RouteFilesHelpers::routeNamed($name);

    expect($route)->not->toBeNull()
        ->and(RouteFilesHelpers::routeSnapshot($route))->toBe([
            'name' => $name,
            'uri' => $uri,
            'methods' => $methods,
            'middleware' => $middleware,
            'domain' => $onLandlordDomain ? config('platform.host') : null,
        ]);
})->with([
    ['orders.index', 'orders', ['GET', 'HEAD'], ['web', 'auth', 'can:' . Permission::ORDERS_READ->value], false],
]);

it('checks the request forgery token on the web group and on the landlord group', function (): void {
    $groups = app('router')->getMiddlewareGroups();

    expect($groups['web'])->toContain(PreventRequestForgery::class)
        ->and($groups['web_landlord'])->toContain(PreventRequestForgery::class);
});

it('builds the landlord group from the web group, so the two cannot drift apart', function (): void {
    $groups = app('router')->getMiddlewareGroups();
    $web = Narrow::strings($groups['web']);
    $landlord = Narrow::strings($groups['web_landlord']);

    expect(array_values(array_diff($landlord, $web)))->toBe([])
        ->and($landlord)->not->toContain('tenant')
        ->and($landlord)->toContain('throttle:reads', 'throttle:writes');
});
