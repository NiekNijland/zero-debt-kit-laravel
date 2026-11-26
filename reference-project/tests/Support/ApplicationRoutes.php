<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionFunction;

/**
 * The routes of the application as the coverage tests read them: the router holds every route (a package's, a test's), and the routes of the application
 * are the ones whose controller class or closure is declared in one of the folders handed in. That replaces a list of package namespaces and of test
 * routes to leave out, which is an exception list in disguise.
 */
final class ApplicationRoutes
{
    /**
     * @param list<string> $folders the folders of the application that hold a controller or a route closure, relative to the base path (`app`, `routes`)
     * @return list<RoutingRoute>
     */
    public static function all(array $folders): array
    {
        return array_values(array_filter(Route::getRoutes()->getRoutes(), static fn (RoutingRoute $route): bool => self::isOwned($route, $folders)));
    }

    /**
     * A route for an invokable controller, as a failing example needs it. `new Route($methods, $uri, SomeController::class)` is no controller route: only
     * `Router::addRoute` turns an invokable class name into `Class@__invoke`, so a route built by hand with the class name has no controller class and
     * `signatureParameters()` reflects a method called `null`.
     *
     * @param list<string> $methods
     */
    public static function forController(array $methods, string $uri, string $controller): RoutingRoute
    {
        return new RoutingRoute($methods, $uri, ['uses' => $controller . '@__invoke', 'controller' => $controller . '@__invoke']);
    }

    /**
     * @param list<string> $folders
     */
    public static function isOwned(RoutingRoute $route, array $folders): bool
    {
        $source = self::sourceFile($route);

        return $source !== null && array_any($folders, static fn (string $folder): bool => str_starts_with($source, base_path($folder) . '/'));
    }

    public static function identifier(RoutingRoute $route): string
    {
        $name = $route->getName();

        return $name === null || str_starts_with($name, 'generated::') ? $route->uri() : $name;
    }

    /**
     * @return list<string> the middleware of a route with the groups expanded, the closures left out
     */
    public static function middleware(RoutingRoute $route): array
    {
        return array_values(array_filter(Route::gatherRouteMiddleware($route), is_string(...)));
    }

    /**
     * The first folder of the controller below the controller root (`Admin` in `App\Http\Controllers\Admin\Orders\ShowController`), null for a route that
     * has no controller below it.
     */
    public static function area(RoutingRoute $route, string $controllers): ?string
    {
        $controller = $route->getControllerClass();

        if ($controller === null || ! str_starts_with($controller, $controllers . '\\')) {
            return null;
        }

        return explode('\\', substr($controller, strlen($controllers) + 1))[0];
    }

    private static function sourceFile(RoutingRoute $route): ?string
    {
        $uses = $route->getAction('uses');
        $controller = $route->getControllerClass();

        $file = match (true) {
            $uses instanceof Closure => new ReflectionFunction($uses)->getFileName(),
            $controller !== null && class_exists($controller) => new ReflectionClass($controller)->getFileName(),
            default => false,
        };

        return is_string($file) ? $file : null;
    }
}
