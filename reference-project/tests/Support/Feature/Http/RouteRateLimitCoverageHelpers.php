<?php

declare(strict_types=1);

namespace Tests\Support\Feature\Http;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionFunction;

final class RouteRateLimitCoverageHelpers
{
    public static function rateLimitOutOfScope(RoutingRoute $route): bool
    {
        $uses = $route->getAction('uses');
        $controller = $route->getControllerClass();
        $source = match (true) {
            $uses instanceof Closure => new ReflectionFunction($uses)->getFileName(),
            $controller !== null && class_exists($controller) => new ReflectionClass($controller)->getFileName(),
            default => false,
        };

        return is_string($source) && array_any(RATE_LIMIT_OUT_OF_SCOPE_SOURCES, static fn (string $directory): bool => str_starts_with($source, base_path($directory)));
    }

    public static function rateLimitNamedRoute(string $name): RoutingRoute
    {
        return Route::getRoutes()->getByName($name) ?? throw new InvalidArgumentException("No route is named {$name}");
    }

    public static function rateLimitRouteIdentifier(RoutingRoute $route): string
    {
        $name = $route->getName();

        return $name === null || str_starts_with($name, 'generated::') ? $route->uri() : $name;
    }

    /**
     * @return list<string>
     */
    public static function rateLimitRouteLimiters(RoutingRoute $route): array
    {
        return array_values(collect(Route::gatherRouteMiddleware($route))
            ->filter(static fn (mixed $middleware): bool => is_string($middleware) && ($middleware === ThrottleRequests::class || str_starts_with($middleware, ThrottleRequests::class . ':')))
            ->map(static fn (string $middleware): string => $middleware === ThrottleRequests::class ? 'package' : Str::after($middleware, ':'))
            ->all());
    }

    /**
     * @return list<string>
     */
    public static function rateLimitRouteCategories(RoutingRoute $route): array
    {
        return array_values(array_diff(self::rateLimitRouteLimiters($route), ['app', 'reads', 'writes']));
    }

    public static function rateLimitRouteProblem(RoutingRoute $route): ?string
    {
        $limiters = self::rateLimitRouteLimiters($route);
        $own = self::rateLimitRouteCategories($route);
        $accepted = [...array_keys(Config::array('rate_limiting.actions')), 'package'];

        if (count($own) > 1) {
            return 'counts in more than one bucket: ' . implode(', ', $own);
        }

        if ($own === []) {
            $reads = array_intersect($route->methods(), ['GET', 'HEAD', 'OPTIONS']) !== [];
            $writes = array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']) !== [];

            return (! $reads || in_array('reads', $limiters, true)) && (! $writes || in_array('writes', $limiters, true))
                ? null
                : 'has no rate limit category';
        }

        if (in_array($own[0], $accepted, true)) {
            return null;
        }

        if (array_key_exists($own[0], RATE_LIMIT_DEDICATED_LIMITERS)) {
            return collect(RATE_LIMIT_DEDICATED_LIMITERS[$own[0]]['routes'])->contains(static fn (string $pattern): bool => Str::is($pattern, self::rateLimitRouteIdentifier($route)))
                ? null
                : 'uses the dedicated limiter ' . $own[0] . ' outside the routes it is meant for';
        }

        return 'uses a limiter that is not a category: ' . $own[0];
    }
}
