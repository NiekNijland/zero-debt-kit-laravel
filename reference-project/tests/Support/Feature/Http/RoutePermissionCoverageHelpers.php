<?php

declare(strict_types=1);

namespace Tests\Support\Feature\Http;

use Closure;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Reflector;
use Illuminate\Support\Str;
use ReflectionFunction;
use ReflectionParameter;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Tests\Support\InstancesOf;
use Tests\Support\Narrow;

final class RoutePermissionCoverageHelpers
{
    public static function permissionRegisteredByATest(RoutingRoute $route): bool
    {
        $uses = $route->getAction('uses');

        return $uses instanceof Closure
            && str_starts_with((string) new ReflectionFunction($uses)->getFileName(), base_path('tests'));
    }

    public static function permissionRegisteredByAPackage(RoutingRoute $route): bool
    {
        $controller = $route->getControllerClass();

        return $controller !== null && array_any(ROUTE_PERMISSION_PACKAGE_NAMESPACES, static fn (string $namespace): bool => str_starts_with($controller, $namespace));
    }

    public static function permissionRouteIdentifier(RoutingRoute $route): string
    {
        $name = $route->getName();

        return $name === null || str_starts_with($name, 'generated::') ? $route->uri() : $name;
    }

    /**
     * @return list<string>
     */
    public static function permissionMiddleware(RoutingRoute $route): array
    {
        return array_values(array_filter(Route::gatherRouteMiddleware($route), is_string(...)));
    }

    public static function permissionRouteIsInScope(RoutingRoute $route): bool
    {
        return collect(self::permissionMiddleware($route))->contains(static fn (string $middleware): bool => in_array(Str::before($middleware, ':'), ROUTE_PERMISSION_IN_SCOPE, true));
    }

    /**
     * @return list<string>
     */
    public static function permissionRouteChecks(RoutingRoute $route): array
    {
        return array_values(collect(self::permissionMiddleware($route))
            ->filter(static fn (string $middleware): bool => str_contains($middleware, ':') && in_array(Str::before($middleware, ':'), [Authorize::class, PermissionMiddleware::class], true))
            ->reject(static fn (string $middleware): bool => in_array($middleware, ROUTE_PERMISSION_AREA_GATES, true))
            ->all());
    }

    /**
     * @return list<string>
     */
    public static function permissionPolicyModels(RoutingRoute $route): array
    {
        return array_values(collect(InstancesOf::list($route->signatureParameters(['subClass' => UrlRoutable::class]), ReflectionParameter::class))
            ->filter(static fn (ReflectionParameter $parameter): bool => array_intersect([$parameter->getName(), Str::snake($parameter->getName())], Narrow::strings($route->parameterNames())) !== []
                && Gate::getPolicyFor((string) Reflector::getParameterClassName($parameter)) !== null)
            ->map(static fn (ReflectionParameter $parameter): string => $parameter->getName())
            ->all());
    }

    public static function permissionRouteIsAuthorized(RoutingRoute $route): bool
    {
        $checks = self::permissionRouteChecks($route);
        $models = self::permissionPolicyModels($route);

        if ($models === []) {
            return $checks !== [];
        }

        return collect($models)->contains(static fn (string $model): bool => collect($checks)->contains(static fn (string $check): bool => in_array($model, array_slice(explode(',', Str::after($check, ':')), 1), true)));
    }

    /**
     * @return list<string>
     */
    public static function permissionUncoveredRoutes(): array
    {
        return array_values(collect(Route::getRoutes()->getRoutes())
            ->reject(static fn (RoutingRoute $route): bool => self::permissionRegisteredByATest($route) || self::permissionRegisteredByAPackage($route))
            ->filter(static fn (RoutingRoute $route): bool => self::permissionRouteIsInScope($route) && ! self::permissionRouteIsAuthorized($route))
            ->map(static fn (RoutingRoute $route): string => self::permissionRouteIdentifier($route))
            ->unique()
            ->all());
    }
}
