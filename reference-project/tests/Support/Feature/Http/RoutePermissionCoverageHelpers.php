<?php

declare(strict_types=1);

namespace Tests\Support\Feature\Http;

use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Reflector;
use Illuminate\Support\Str;
use ReflectionParameter;
use Tests\Support\ApplicationRoutes;
use Tests\Support\InstancesOf;
use Tests\Support\Narrow;

final class RoutePermissionCoverageHelpers
{
    /**
     * Every route of the application that sits behind authentication and carries no permission or policy ability of its own. A route of the public area
     * needs none (nobody holds a permission for a page for guests), and a route of a package or of a test is no route of the application.
     *
     * @param list<string> $folders the folders that hold a controller or a route closure, relative to the base path
     * @param string $controllers the controller root namespace, for the area of a route
     * @param string|null $publicArea the first folder below the controller root whose routes are for guests, null when the application has none
     * @param list<string> $authenticating the middleware classes that put a route in scope (`Authenticate::class`)
     * @param list<string> $checking the middleware classes that carry a permission or a policy ability (`Authorize::class`, a package's permission middleware)
     * @param list<string> $areaGates the full middleware strings that open a whole area and so are no permission of the route itself
     * @return list<string>
     */
    public static function uncoveredRoutes(array $folders, string $controllers, ?string $publicArea, array $authenticating, array $checking, array $areaGates): array
    {
        return array_values(array_unique(array_map(
            ApplicationRoutes::identifier(...),
            array_filter(
                ApplicationRoutes::all($folders),
                static fn (RoutingRoute $route): bool => ApplicationRoutes::area($route, $controllers) !== $publicArea
                    && self::isInScope($route, $authenticating)
                    && ! self::isAuthorized($route, $checking, $areaGates),
            ),
        )));
    }

    /**
     * @param list<string> $authenticating
     */
    public static function isInScope(RoutingRoute $route, array $authenticating): bool
    {
        return array_any(ApplicationRoutes::middleware($route), static fn (string $middleware): bool => in_array(Str::before($middleware, ':'), $authenticating, true));
    }

    /**
     * A route is authorized by a check that is no area gate. When the route names a model in its URL that has a policy, the check must be the ability on that
     * model (`can:view,note`): a permission string alone does not stop one user from reading another's record.
     *
     * @param list<string> $checking
     * @param list<string> $areaGates
     */
    public static function isAuthorized(RoutingRoute $route, array $checking, array $areaGates): bool
    {
        $checks = array_values(array_filter(
            ApplicationRoutes::middleware($route),
            static fn (string $middleware): bool => str_contains($middleware, ':')
                && in_array(Str::before($middleware, ':'), $checking, true)
                && ! in_array($middleware, $areaGates, true),
        ));
        $models = self::policyModels($route);

        if ($models === []) {
            return $checks !== [];
        }

        return array_any($models, static fn (string $model): bool => array_any(
            $checks,
            static fn (string $check): bool => in_array($model, array_slice(explode(',', Str::after($check, ':')), 1), true),
        ));
    }

    /**
     * @return list<string> the names of the URL parameters that bind a model with a policy
     */
    private static function policyModels(RoutingRoute $route): array
    {
        $parameters = InstancesOf::list($route->signatureParameters(['subClass' => UrlRoutable::class]), ReflectionParameter::class);
        $names = Narrow::strings($route->parameterNames());

        return array_values(array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName(),
            array_filter(
                $parameters,
                static fn (ReflectionParameter $parameter): bool => array_intersect([$parameter->getName(), Str::snake($parameter->getName())], $names) !== []
                    && Gate::getPolicyFor((string) Reflector::getParameterClassName($parameter)) !== null,
            ),
        ));
    }
}
