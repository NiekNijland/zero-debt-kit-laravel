<?php

declare(strict_types=1);

namespace Tests\Support\Feature\Routing;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use LogicException;

final class RouteFilesHelpers
{
    public static function routeNamed(string $name): RoutingRoute
    {
        return Route::getRoutes()->getByName($name) ?? throw new LogicException('No route is named ' . $name . '.');
    }

    /**
     * @return array{name: string|null, uri: string, methods: list<string>, middleware: list<string>, domain: string|null}
     */
    public static function routeSnapshot(RoutingRoute $route): array
    {
        return [
            'name' => $route->getName(),
            'uri' => $route->uri(),
            'methods' => array_values($route->methods()),
            'middleware' => array_values($route->middleware()),
            'domain' => $route->getDomain(),
        ];
    }
}
